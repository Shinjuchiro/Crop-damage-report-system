<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DamageReport;
use App\Models\Validation;
use App\Models\ValidationPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Technician Field Inspection
 *
 * Proposal sections 40 to 52, and 91.5 for the review step.
 *
 * The single most important rule in this module, from section 29:
 * the technician does NOT create a second damage report. They open the
 * report the FARMER submitted and add an inspection to it. Everything the
 * farmer wrote stays exactly as they wrote it.
 *
 * The workflow, as the approved mockup lays it out:
 *
 *   View details -> Start Inspection
 *     -> Step 1  Capture photos (wide shot and close-up)
 *     -> Step 2  Assess damage severity and the assessed percentage
 *     -> Step 3  Pin the verified location
 *     -> Step 4  Review the summary, confirm, submit
 */
class InspectionController extends Controller
{
    private const PHOTO_DISK = 'public';
    private const PHOTO_DIR  = 'inspections';
    private const MAX_EXTRA  = 8;

    /**
     * When the technician's own figure is this far from the farmer's
     * estimate, notes stop being optional.
     *
     * The reasoning: if the two of you basically agree, the numbers speak
     * for themselves. If you are contradicting the farmer by a wide margin,
     * the office needs to read WHY before they decide on assistance, and so
     * does the farmer if they ever question the result.
     */
    private const NOTES_REQUIRED_GAP = 15;

    /**
     * The full farmer-submitted report, read only (section 40).
     */
    public function show(DamageReport $report)
    {
        $this->authorizeAssignment($report);

        return view('technician.reports.show', [
            'report' => $report->load([
                'farmer.barangay', 'farmer.association', 'farmer.user',
                'crops.crop', 'disasters', 'photos',
                'reportedBarangay', 'validation.photos',
            ]),
        ]);
    }

    /**
     * Start Inspection (section 41).
     *
     * Records who started it and when, and moves the report to
     * Under Verification so the office can see it is being worked on.
     */
    public function start(DamageReport $report)
    {
        $this->authorizeAssignment($report);

        if ($report->status !== 'assigned') {
            return back()->withErrors([
                'inspection' => 'This report is not waiting to be inspected.',
            ]);
        }

        DB::transaction(function () use ($report) {
            // firstOrCreate, not create: if a technician taps Start twice
            // on a slow connection we must not end up with two inspection
            // rows hanging off one report.
            Validation::firstOrCreate(
                ['damage_report_id' => $report->id],
                [
                    'technician_id'         => Auth::id(),
                    'inspection_started_at' => now(),
                ]
            );

            $report->update(['status' => 'under_verification']);

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'Started inspection of ' . $report->reference,
                'target_table' => 'damage_reports',
                'target_id'    => $report->id,
                'created_at'   => now(),
            ]);
        });

        return redirect()
            ->route('technician.inspection.edit', $report)
            ->with('status', 'Inspection started. Record what you find on the farm.');
    }

    /**
     * The inspection form: four steps on a phone, one long page on a laptop.
     */
    public function edit(DamageReport $report)
    {
        $this->authorizeAssignment($report);

        $validation = $report->validation;

        // You cannot fill in an inspection that was never started.
        if (! $validation) {
            return redirect()
                ->route('technician.reports.show', $report)
                ->withErrors(['inspection' => 'Press Start Inspection first.']);
        }

        // Once submitted it is a record, not a draft.
        if ($validation->validated_at) {
            return redirect()
                ->route('technician.reports.show', $report)
                ->withErrors(['inspection' => 'This inspection has already been submitted.']);
        }

        return view('technician.inspections.edit', [
            'report'     => $report->load(['farmer.barangay', 'farmer.user', 'crops.crop', 'disasters', 'photos', 'reportedBarangay']),
            'validation' => $validation->load('photos'),
            'severities' => Validation::SEVERITY_SCALE,
            'notesGap'   => self::NOTES_REQUIRED_GAP,
        ]);
    }

    /**
     * Confirm and submit the inspection (sections 51 and 52).
     */
    public function update(Request $request, DamageReport $report)
    {
        $this->authorizeAssignment($report);

        $validation = $report->validation;

        if (! $validation || $validation->validated_at) {
            return redirect()
                ->route('technician.reports.show', $report)
                ->withErrors(['inspection' => 'This inspection cannot be submitted again.']);
        }

        $data = $request->validate([
            'severity' => ['required', Rule::in(array_keys(Validation::SEVERITY_SCALE))],

            // The technician's own figure. Section 45: this never overwrites
            // the farmer's estimate, it is stored beside it.
            'assessed_damage_percent' => ['required', 'numeric', 'min:1', 'max:100'],

            // Optional by default, as the approved mockup shows it. It becomes
            // required further down when the technician's figure is a long way
            // from the farmer's.
            'notes' => ['nullable', 'string', 'max:2000'],

            // Section 47: the verified location. Required here, unlike the
            // farmer's, because the technician is standing on the farm.
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],

            // Step 1 of the mockup asks for two named shots. The wide shot is
            // the proof the farm was actually visited, so that one is required.
            'photo_wide'    => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:5120'],
            'photo_closeup' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:5120'],

            'photos'   => ['nullable', 'array', 'max:' . self::MAX_EXTRA],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,heic', 'max:5120'],
        ], [
            'severity.required'                => 'Please choose the damage severity.',
            'assessed_damage_percent.required' => 'Please enter your assessed damage percentage.',
            'latitude.required'                => 'Please capture or enter the verified farm location.',
            'longitude.required'               => 'Please capture or enter the verified farm location.',
            'photo_wide.required'              => 'Please take the wide shot of the affected area. It is the record that the farm was visited.',
            'photo_wide.max'                   => 'The wide shot must be 5 MB or smaller.',
            'photo_closeup.max'                => 'The close-up must be 5 MB or smaller.',
            'photos.*.max'                     => 'Each additional photo must be 5 MB or smaller.',
        ]);

        $this->assertSeverityMatchesPercent($data['severity'], (float) $data['assessed_damage_percent']);
        $this->assertNotesExplainDisagreement($report, $data);

        DB::transaction(function () use ($request, $report, $validation, $data) {

            $validation->update([
                'severity'                => $data['severity'],
                'assessed_damage_percent' => $data['assessed_damage_percent'],
                'notes'                   => $data['notes'] ?? null,
                'latitude'                => $data['latitude'],
                'longitude'               => $data['longitude'],
                'validated_at'            => now(),
            ]);

            // Section 36 and 43: the technician's photos are stored separately
            // from the farmer's. Two people, two sets of evidence, never mixed.
            // Each one is tagged so the record still makes sense months later.
            $this->storePhoto($validation, $report, $request->file('photo_wide'), 'wide');
            $this->storePhoto($validation, $report, $request->file('photo_closeup'), 'closeup');

            foreach ($request->file('photos', []) as $extra) {
                $this->storePhoto($validation, $report, $extra, 'other');
            }

            $report->update(['status' => 'verified']);

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'Submitted inspection for ' . $report->reference
                                  . ': ' . ucfirst($data['severity']) . ', '
                                  . $data['assessed_damage_percent'] . '% assessed',
                'target_table' => 'validations',
                'target_id'    => $validation->id,
                'created_at'   => now(),
            ]);
        });

        return redirect()
            ->route('technician.reports.show', $report)
            ->with('status', 'Inspection successfully submitted.');
    }

    /* ==================================================================
     | Helper methods
     ================================================================== */

    /**
     * Saves one uploaded photo against the inspection.
     *
     * Null is a normal answer here, not a mistake: the close-up and the
     * extra photos are optional, so this just does nothing for those.
     */
    private function storePhoto(Validation $validation, DamageReport $report, $file, string $shotType): void
    {
        if (! $file) {
            return;
        }

        ValidationPhoto::create([
            'validation_id' => $validation->id,
            'shot_type'     => $shotType,
            'file_path'     => $file->store(self::PHOTO_DIR . '/' . $report->id, self::PHOTO_DISK),
            'uploaded_at'   => now(),
        ]);
    }

    /**
     * The severity band and the percentage have to agree.
     *
     * Section 44 defines the bands (Slight 1-25, Moderate 26-50,
     * Partial 51-99, Total 100). Picking "Slight" and then typing 90%
     * would leave the office with a report that contradicts itself, and
     * whichever value they trusted would be wrong.
     */
    private function assertSeverityMatchesPercent(string $severity, float $percent): void
    {
        $bands = [
            'slight'   => [1, 25],
            'moderate' => [26, 50],
            'partial'  => [51, 99],
            'total'    => [100, 100],
        ];

        [$min, $max] = $bands[$severity];

        if ($percent < $min || $percent > $max) {
            throw ValidationException::withMessages([
                'assessed_damage_percent' => ucfirst($severity) . ' damage means '
                    . ($min === $max ? $min . '%' : $min . ' to ' . $max . '%')
                    . '. Either change the percentage or choose a different severity.',
            ]);
        }
    }

    /**
     * If you are contradicting the farmer, say why.
     *
     * Notes are optional in the normal case. But when the technician's
     * assessed percentage is a long way off the farmer's own estimate, the
     * office is going to be deciding assistance on the basis of the smaller
     * number, and "because the technician said so" is not good enough to put
     * in front of a farmer who disagrees.
     */
    private function assertNotesExplainDisagreement(DamageReport $report, array $data): void
    {
        $farmerEstimate = $report->crops()->avg('estimated_damage_percent');

        if ($farmerEstimate === null) {
            return;
        }

        $gap = abs((float) $farmerEstimate - (float) $data['assessed_damage_percent']);

        if ($gap >= self::NOTES_REQUIRED_GAP && blank($data['notes'] ?? null)) {
            throw ValidationException::withMessages([
                'notes' => 'Your assessment is ' . round($gap) . ' points away from the farmer\'s estimate of '
                    . round($farmerEstimate) . '%. Please write a short note explaining what you actually found.',
            ]);
        }
    }

    /**
     * A technician may only touch reports assigned to them.
     *
     * Checked on every action, not just hidden from the list. Otherwise
     * typing another report id in the URL would be enough to inspect
     * somebody else's assignment.
     */
    private function authorizeAssignment(DamageReport $report): void
    {
        abort_unless($report->assigned_technician_id === Auth::id(), 403);
    }
}
