<?php

namespace App\Http\Controllers\Farmer;

use App\Http\Controllers\Controller;
use App\Http\Requests\FarmerCorrectionRequest;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Crop;
use App\Models\Farmer;
use App\Models\FarmerMainCrop;
use App\Models\NotificationBroadcast;
use App\Support\MembershipApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The farmer's own view of their application while it is still being
 * decided, and the one thing they can do about it (revision step 6).
 *
 * WHY THIS LIVES OUTSIDE THE 'active' MIDDLEWARE
 *
 * Every other farmer route is behind 'active', which means approved. These
 * three cannot be: the whole point is to reach a farmer whose account is
 * still pending. They sit in the 'auth' group in routes/web.php alongside
 * the holding screens, and each one re-checks that the signed-in user is a
 * farmer and that the application is theirs, because a middleware is not
 * doing it for them.
 *
 * WHAT WAS HERE BEFORE
 *
 * A closure returning view('auth.pending'), which said "your registration
 * is awaiting review by the Municipal Agriculture Office" to everybody.
 * That was true when there was one queue. It is now wrong in three of the
 * four stages, and in the fourth it is actively misleading: a farmer who
 * has been asked to correct something would have sat there waiting for a
 * decision that was never coming, because the system was waiting for them.
 */
class ApplicationController extends Controller
{
    /**
     * The holding screen: where the application has got to, and what, if
     * anything, the farmer has to do.
     *
     * Deliberately tolerant of a user who is not a farmer at all. A
     * technician or officer whose account the office has set to pending or
     * inactive is redirected here by the 'active' middleware too, and they
     * have no farmer row; they get the generic screen rather than an error.
     */
    public function pending(Request $request)
    {
        $farmer = $this->farmerOrNull();

        /* An approved farmer has no business on a holding screen. Nothing
           routes them here - EnsureActiveAccount only sends pending
           accounts - but the URL is reachable by hand and by the back
           button after approval, and a page explaining that their
           application is being reviewed would be the last thing a farmer
           sees after being told it was approved. */
        if ($farmer && Auth::user()->status === 'active') {
            return redirect()->route('farmer.dashboard');
        }

        return view('auth.pending', [
            'farmer' => $farmer,
            'status' => $farmer ? MembershipApplication::forFarmer($farmer) : null,
        ]);
    }

    /**
     * The screen a rejected or archived account lands on.
     *
     * WHY THIS IS NOW A CONTROLLER (Oct 2026)
     *
     * It was a closure returning view('auth.blocked'), which said "this
     * account is currently inactive or was not approved, please contact the
     * Municipal Agriculture Office" and nothing else. The revision requires
     * that a farmer can see why their application was rejected, and that was
     * not happening anywhere:
     *
     *   - A rejected farmer CAN sign in. EnsureActiveAccount authenticates
     *     them and then redirects them here, so this page is the only thing
     *     they ever see.
     *   - The rejection notification is in-app only, and the notifications
     *     page sits behind the 'active' middleware. So the office wrote a
     *     reason, the system sent it, and the one person it was addressed to
     *     could not reach it. Telling somebody to ring the office to find out
     *     what the office already wrote down is not a workflow.
     *
     * So the reason is shown here, read from farmers.decision_note, which the
     * same revision made a required field on rejection.
     *
     * Tolerant of a non-farmer for the same reason pending() is: a technician
     * or officer whose account the office archived is redirected here too and
     * has no farmer row. They get the generic message.
     */
    public function blocked()
    {
        $farmer = $this->farmerOrNull();

        /* An account that is no longer blocked should not be looking at this
           page; the URL is reachable by hand and by the back button. Sent to
           the holding screen rather than the dashboard, because that screen
           already works out where a farmer actually belongs. */
        if (! in_array(Auth::user()?->status, ['rejected', 'inactive'], true)) {
            return redirect()->route('account.pending');
        }

        return view('auth.blocked', [
            'farmer'   => $farmer,
            'status'   => $farmer ? MembershipApplication::forFarmer($farmer) : null,
            /* Only ever shown for a rejection. An ARCHIVED application is
               the office taking a record out of its queue, which is an
               internal filing action and not a decision about this person;
               showing them a note written in that context would be
               answering a question they did not ask. */
            'reason'   => $farmer && MembershipApplication::forFarmer($farmer) === MembershipApplication::REJECTED
                ? $farmer->decision_note
                : null,
            'decidedAt' => $farmer?->decided_at,
        ]);
    }

    /**
     * Membership Application, as a sidebar destination (revision section 4).
     *
     * Section 4 lists "Membership Application" among the farmer's navigation
     * items and there was no such page. There were two half-answers: the
     * holding screen at account.pending, which only a farmer who cannot yet
     * use the system ever sees, and the correction form, which only exists
     * while an application has been returned. An approved farmer had nowhere
     * at all to look up their own membership.
     *
     * WHO ACTUALLY READS THIS
     *
     * It sits inside the farmer group, behind the 'active' middleware, so by
     * definition the reader is an approved farmer. The question they come
     * here with is not "what is my status" - they can use the system, so they
     * know - it is the one the office gets asked on the phone: which
     * association am I under, who signed off on me, and when. So the page
     * leads with the association and the two handovers rather than with a
     * status badge.
     *
     * The two screens are complementary rather than duplicated: a farmer who
     * is pending, returned or rejected cannot reach this one at all, and
     * account.pending / auth.blocked are written for them. Nothing is
     * repeated between them.
     *
     * Section 8's farmer rights ("View application status", "View correction
     * remarks", "View rejection reason") are covered across the three
     * screens, each at the point the farmer can actually get to it.
     */
    public function status()
    {
        $farmer = $this->farmerOrNull();

        /* Not a farmer at all. The 'active' middleware lets any approved
           account into its own role's group, but this route is in the
           farmer's, so this only happens if the office switched somebody's
           role without clearing their farmer row. A plain 403 says more than
           a page of empty fields. */
        abort_if($farmer === null, 403, 'Only a farmer has a membership application.');

        $farmer->load(['association', 'barangay', 'mainCrops.crop', 'forwardedBy', 'decidedBy']);

        return view('farmer.application.status', [
            'farmer'  => $farmer,
            'status'  => MembershipApplication::forFarmer($farmer),
            // The same audit-log reader the office's own screens use, so the
            // farmer sees the same history rather than a second, friendlier
            // version of it that could drift.
            'history' => MembershipApplication::history($farmer),
        ]);
    }

    /**
     * The correction form.
     *
     * Prefilled with what the farmer already submitted, because a form that
     * came back empty would be asking them to type their whole registration
     * again to fix one field, and the fields they retyped from memory would
     * be the next thing to go wrong.
     */
    public function edit()
    {
        $farmer = $this->returnedFarmer();

        return view('farmer.application.correct', [
            'farmer'    => $farmer->load('mainCrops.crop'),
            'barangays' => Barangay::orderBy('name')->get(),
            // inDisplayOrder() so the pickers read Rice, Corn, HVCC like
            // everywhere else in the system (revision step 3).
            'crops'     => Crop::active()->inDisplayOrder()->get(),
        ]);
    }

    /**
     * Save the corrections and send the application back round.
     *
     * Back to the ASSOCIATION, always, whoever returned it. That is the
     * decision the office made and the reason is the one path back in: an
     * officer is never handed an application whose corrections they have not
     * seen, and the office never re-reads a version the association has not
     * vouched for.
     */
    public function update(FarmerCorrectionRequest $request)
    {
        $farmer = $this->returnedFarmer();

        $data = $request->validated();

        /* The one rule the form request could not express, because it
           depends on what is already stored rather than on what was sent: a
           land owner has to have a certificate on file. A farmer correcting
           an address keeps the one they uploaded at registration; a farmer
           who has just switched from tenant to land owner has none, and has
           to attach it now. */
        $hasCertificateOnFile = filled($farmer->barangay_certificate_path);

        if ($data['ownership_type'] === 'land_owner'
            && ! $hasCertificateOnFile
            && ! $request->hasFile('barangay_certificate')) {
            return back()
                ->withInput()
                ->withErrors(['barangay_certificate' =>
                    'Land owners have to upload a Barangay Certificate proving they own the land.']);
        }

        // Stored before the transaction, since a file write is not rolled
        // back by the database anyway. The old file is left where it is: it
        // is the document the association already saw, and deleting it
        // would take away the only way to tell what was replaced.
        // 'local' is the private disk (storage/app/private). See the note in
        // Auth\RegisterController and AttachmentController: a certificate is
        // not a public file.
        $certificatePath = $request->hasFile('barangay_certificate')
            ? $request->file('barangay_certificate')->store('barangay-certificates', 'local')
            : $farmer->barangay_certificate_path;

        DB::transaction(function () use ($farmer, $data, $certificatePath) {
            $farmer->update([
                'barangay_id'               => $data['barangay_id'],
                'first_name'                => $data['first_name'],
                'middle_name'               => $data['middle_name'] ?? null,
                'last_name'                 => $data['last_name'],
                'date_of_birth'             => $data['date_of_birth'],
                'sex'                       => $data['sex'],
                'ownership_type'            => $data['ownership_type'],
                // Cleared rather than left behind when the farmer switches
                // to land owner, so a land owner's record does not keep
                // somebody else's name on it as their landlord.
                'landowner_name'            => $data['ownership_type'] === 'tenant' ? ($data['landowner_name'] ?? null) : null,
                'landowner_contact'         => $data['ownership_type'] === 'tenant' ? ($data['landowner_contact'] ?? null) : null,
                'landowner_location'        => $data['ownership_type'] === 'tenant' ? ($data['landowner_location'] ?? null) : null,
                'barangay_certificate_path' => $certificatePath,
                'address'                   => $data['address'],
                'farm_size_hectares'        => $data['farm_size_hectares'] ?? null,
            ]);

            // The phone number is on the account, not the farmer row, and it
            // is the number the office rings and the SMS alerts dial, so a
            // wrong one is worth being able to fix here.
            $farmer->user->update(['phone_number' => $data['phone_number']]);

            /* Main crops are replaced wholesale rather than diffed. They
               are a list with no history of their own and nothing points at
               a farmer_main_crops row, so working out which to add and
               which to drop would be effort spent to reach the same three
               rows. Inside the transaction, so a failure cannot leave a
               farmer with no crops at all. */
            FarmerMainCrop::where('farmer_id', $farmer->id)->delete();

            foreach ($data['crops'] as $crop) {
                FarmerMainCrop::create([
                    'farmer_id'    => $farmer->id,
                    'crop_id'      => $crop['crop_id'],
                    'crop_specify' => $crop['crop_specify'] ?? null,
                ]);
            }

            $farmer->resubmitToAssociation();

            AuditLog::create([
                'user_id'      => Auth::id(),
                'action'       => 'Corrected and resubmitted membership application (pass '
                    . $farmer->resubmission_count . ')',
                'target_table' => 'farmers',
                'target_id'    => $farmer->id,
                'created_at'   => now(),
            ]);
        });

        $this->notifyAssociationOfResubmission($farmer->fresh());

        return redirect()->route('account.pending')->with('status',
            'Your corrected application has been sent back to your Farmers\' Association for review.');
    }

    /* =====================================================================
     | Helpers
     ===================================================================== */

    private function farmerOrNull(): ?Farmer
    {
        if (Auth::user()?->role !== 'farmer') {
            return null;
        }

        return Farmer::query()
            ->notDeleted()
            ->with(['user', 'association', 'returnedBy'])
            ->where('user_id', Auth::id())
            ->first();
    }

    /**
     * The signed-in farmer's application, and only if it is actually waiting
     * on them.
     *
     * Both halves are the authorisation. The where on user_id is what stops
     * one farmer correcting another's application, and it is a where rather
     * than a check afterwards so there is no comparison to forget. The stage
     * check is what stops a farmer editing their registration at a moment
     * when somebody is reading it: an application under review has to stop
     * changing underneath the officer reading it, the same rule the damage
     * report follows once a technician is assigned.
     */
    private function returnedFarmer(): Farmer
    {
        $farmer = $this->farmerOrNull();

        abort_if($farmer === null, 403, 'Only a farmer can correct a membership application.');

        abort_unless(
            MembershipApplication::forFarmer($farmer) === MembershipApplication::RETURNED,
            403,
            'This application is not waiting for your corrections right now.'
        );

        return $farmer;
    }

    /**
     * Tell the association the corrected application is back on their desk.
     *
     * In-app only and 'normal' priority: section 68 reserves SMS for urgent
     * and critical matters. Runs after the transaction has committed and
     * never throws, because the resubmission has already happened and a
     * notification that could not be sent must not make it look as though
     * it had not.
     */
    private function notifyAssociationOfResubmission(Farmer $farmer): void
    {
        if (! $farmer->association_id) {
            return;
        }

        try {
            NotificationBroadcast::create([
                'title'       => 'Corrected Application Resubmitted',
                'message'     => $farmer->full_name . ' has corrected their application and resubmitted it '
                    . 'for your review. This is pass ' . $farmer->resubmission_count . '.',
                'category'    => 'system',
                'priority'    => 'normal',
                'target_type' => 'specific_association',
                'target_id'   => $farmer->association_id,
                'status'      => 'draft',
                'created_by'  => Auth::id(),
            ])->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify association ' . $farmer->association_id
                . ' of a resubmitted application for farmer ' . $farmer->id . ': ' . $e->getMessage());
        }
    }
}
