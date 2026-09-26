<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Concerns\SyncsDisasterLinks;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DamageReport;
use App\Models\Disaster;
use App\Models\NotificationBroadcast;
use App\Models\Validation;
use App\Models\ValidationPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    use SyncsDisasterLinks;

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
    public function show(Request $request, DamageReport $report)
    {
        $this->authorizeAssignment($report);

        [$backUrl, $backLabel] = $this->backToList($request, $report);

        return view('technician.reports.show', [
            'report' => $report->load([
                'farmer.barangay', 'farmer.association', 'farmer.user',
                'crops.crop', 'disasters', 'photos',
                'reportedBarangay', 'validation.photos',
            ]),
            'backUrl'   => $backUrl,
            'backLabel' => $backLabel,
        ]);
    }

    /**
     * Where the "Back" button at the top of the report details page goes.
     *
     * It used to be hard coded to the dashboard, so a technician who opened
     * a report from Assigned Reports, Validation or Inspection History was
     * dropped on the dashboard afterwards and had to find their way back to
     * the list, losing their filters and their page number on the way.
     *
     * The referring page decides instead. Only this module's own list pages
     * are accepted, matched on path against the named routes, so a link in
     * from anywhere else (a notification, another site, a pasted URL) can
     * never point the button somewhere unexpected. What is returned is the
     * full referring URL rather than route(), so ?status=, ?barangay= and
     * ?page= all survive the round trip and the technician lands back on
     * the same filtered page they left.
     *
     * Remembering it in the session covers the one case the referrer
     * cannot: refresh this page and the referrer becomes this page itself,
     * so without the memory the button would drop back to the dashboard the
     * moment anyone reloaded. It is scoped to the report being viewed, so
     * opening a different report from somewhere else never inherits a stale
     * destination.
     *
     * @return array{0: string, 1: string}
     */
    private function backToList(Request $request, DamageReport $report): array
    {
        $pages = [
            'technician.reports.index'    => 'Back to Assigned Reports',
            'technician.validation.index' => 'Back to Validation',
            'technician.history.index'    => 'Back to Inspection History',
            'technician.damage.index'     => 'Back to Damage Reports',
            'technician.archive.index'    => 'Back to Archive',
            'technician.map.index'        => 'Back to Map',
            'technician.dashboard'        => 'Back to Dashboard',
        ];

        $previous = url()->previous();
        $path     = rtrim((string) strtok($previous, '?'), '/');

        foreach ($pages as $name => $label) {
            if ($path === rtrim(route($name), '/')) {
                $request->session()->put('technician.report_back', [
                    'report' => $report->id,
                    'url'    => $previous,
                    'label'  => $label,
                ]);

                return [$previous, $label];
            }
        }

        $remembered = $request->session()->get('technician.report_back');

        if (is_array($remembered) && ($remembered['report'] ?? null) === $report->id) {
            return [(string) $remembered['url'], (string) $remembered['label']];
        }

        return [route('technician.dashboard'), 'Back to Dashboard'];
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

            // Every currently active disaster, not only ones matching this
            // report's cause - the developer wanted this open regardless of
            // cause, since a technician on-site may know better than the
            // cause dropdown the farmer picked from home. Archived events
            // are left out, same rule the farmer's own form already uses.
            'availableDisasters' => Disaster::active()->orderByDesc('date_start')->orderBy('name')->get(),
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

            // Optional, on purpose (the developer's choice): the technician
            // may add a disaster event the farmer never linked, or uncheck
            // one the farmer got wrong, but is never forced to touch this
            // at all. Any active event, regardless of the report's own
            // cause - see the comment on $availableDisasters in edit().
            'disasters'   => ['nullable', 'array'],
            'disasters.*' => [Rule::exists('disasters', 'id')->whereNull('archived_at')],
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

            $this->syncDisasterLinks($report, $data['disasters'] ?? [], 'technician');

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

        $this->notifyInspectionSubmitted($report);

        return redirect()
            ->route('technician.reports.show', $report)
            ->with('status', 'Inspection successfully submitted.');
    }

    /**
     * Proposal section 66: once a report is verified, both MAO ("validation
     * completed/needs review" / "verified reports ready for assistance
     * allocation") and the farmer ("validation results") should be told.
     * Runs after the transaction above has already committed, and never
     * throws - a notification failure must never make an otherwise-successful
     * inspection appear to fail (see the Sept 2026 notification-system rule).
     *
     * Both are 'normal' priority: a completed inspection is routine, not
     * urgent (section 68). link_type/link_id let each bell open this exact
     * report (NotificationBroadcast::linkUrl()).
     */
    private function notifyInspectionSubmitted(DamageReport $report): void
    {
        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'Report Verified',
                'message'     => $report->reference . ' has been inspected and verified. It is now ready for review and possible assistance allocation.',
                'category'    => 'system',
                'priority'    => 'normal',
                'target_type' => 'all_mao',
                'link_type'   => 'damage_report',
                'link_id'     => $report->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify MAO of verified report ' . $report->id . ': ' . $e->getMessage());
        }

        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'Your Report Was Verified',
                'message'     => 'Your damage report ' . $report->reference . ' has been inspected by a technician '
                    . 'and is now with the Municipal Agriculture Office for review.',
                'category'    => 'system',
                'priority'    => 'normal',
                'target_type' => 'specific_farmer',
                'target_id'   => $report->farmer_id,
                'link_type'   => 'damage_report',
                'link_id'     => $report->id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify farmer of verified report ' . $report->id . ': ' . $e->getMessage());
        }
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
