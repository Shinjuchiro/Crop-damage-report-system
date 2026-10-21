<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\FarmerRegistrationRequest;
use App\Models\Association;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Crop;
use App\Models\Farmer;
use App\Models\FarmerMainCrop;
use App\Models\NotificationBroadcast;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ONLY farmers self-register. Technicians and Association officers are
 * created by the MAO through User Management.
 */
class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register', [
            'barangays'    => Barangay::orderBy('name')->get(),
            // A brand new farmer should never be offered an association or a
            // crop the office has archived out of circulation.
            'associations' => Association::active()->orderBy('name')->get(),
            'crops'        => Crop::active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Is this username or email still free?
     *
     * The registration wizard asks before it lets a farmer leave the Account
     * Details step. Everything else on that step the browser can judge on its
     * own; "already taken" is the one thing only the database knows, and
     * without this the farmer filled in all four steps, uploaded a Barangay
     * Certificate, submitted, and was thrown back to step 2 with the file
     * input emptied - a file input cannot be refilled by the server.
     *
     * Deliberately narrow: it answers yes or no for one username and one
     * email, nothing else, and says nothing about the account behind them.
     * FarmerRegistrationRequest still enforces both rules on submit; this is
     * a courtesy, never the gate. Throttled in routes/web.php.
     */
    public function availability(Request $request)
    {
        $data = $request->validate([
            'username' => ['nullable', 'string', 'max:100'],
            'email'    => ['nullable', 'string', 'max:255'],
        ]);

        $answer = [];

        if (! empty($data['username'])) {
            $answer['username'] = ! User::where('username', $data['username'])->exists();
        }

        if (! empty($data['email'])) {
            $answer['email'] = ! User::where('email', $data['email'])->exists();
        }

        return response()->json($answer);
    }

    public function store(FarmerRegistrationRequest $request)
    {
        $data = $request->validated();

        // Store the barangay certificate first (outside the transaction, since
        // file writes are not rolled back by the database anyway)
        $certificatePath = null;
        if ($request->hasFile('barangay_certificate')) {
            /* 'local' (storage/app/private), not 'public'. A barangay
               certificate carries this farmer's full name and home address,
               and on the public disk it was served by the web server to
               anybody holding the URL, signed in or not. It is read through
               AttachmentController now (revision section 3.4). */
            $certificatePath = $request->file('barangay_certificate')
                ->store('barangay-certificates', 'local');
        }

        $farmer = DB::transaction(function () use ($data, $certificatePath) {
            $user = new User([
                'username'           => $data['username'],
                'email'              => $data['email'],
                'password'           => $data['password'], // auto-hashed by the model cast
                'phone_number'       => $data['phone_number'],
                'status'             => 'pending', // MAO must approve before full access
                'preferred_language' => 'en',
            ]);

            // Set outside the array on purpose: `role` is not mass assignable,
            // so nothing arriving in a request can ever reach it. See User.
            $user->role = 'farmer';
            $user->save();

            $farmer = Farmer::create([
                'user_id'                   => $user->id,
                'association_id'            => $data['association_id'],
                'barangay_id'               => $data['barangay_id'],
                'first_name'                => $data['first_name'],
                'middle_name'               => $data['middle_name'] ?? null,
                'last_name'                 => $data['last_name'],
                'date_of_birth'             => $data['date_of_birth'],
                'sex'                       => $data['sex'],
                'ownership_type'            => $data['ownership_type'],
                'landowner_name'            => $data['landowner_name'] ?? null,
                'landowner_contact'         => $data['landowner_contact'] ?? null,
                'landowner_location'        => $data['landowner_location'] ?? null,
                'barangay_certificate_path' => $certificatePath,
                'address'                   => $data['address'],
                'farm_size_hectares'        => $data['farm_size_hectares'] ?? null,
                /* activity_status starts Active and last_activity_date is
                   deliberately NOT set (revision section 15, Oct 2026).

                   It used to be set to the registration date, because the
                   column was NOT NULL and something had to go in it. That
                   made every brand new farmer look as though they had filed
                   a Crop Planted Report on the day they signed up, and the
                   column is labelled "Last Activity" on five screens.

                   It is nullable now (migration 2024_01_24_000001) and null
                   means exactly "has never filed a planting report". The
                   inactivity clock still starts today, because
                   Farmer::getInactivityMeasuredFromAttribute() falls back to
                   the registration date, which is what section 15 asks for -
                   so nothing is lost by leaving it empty, and the one thing
                   the column claims to record stays true. */
                'activity_status'           => 'active',
            ]);

            foreach ($data['crops'] as $crop) {
                FarmerMainCrop::create([
                    'farmer_id'    => $farmer->id,
                    'crop_id'      => $crop['crop_id'],
                    'crop_specify' => $crop['crop_specify'] ?? null,
                ]);
            }

            AuditLog::create([
                'user_id'      => $user->id,
                'action'       => 'Submitted farmer registration',
                'target_table' => 'farmers',
                'target_id'    => $farmer->id,
                'created_at'   => now(),
            ]);

            return $farmer;
        });

        $this->notifyAssociationOfNewApplication($farmer);

        return redirect()->route('login')->with('status',
            'Registration submitted successfully. Your application has been sent to your '
            . 'Farmers\' Association for review, and will then be verified by the Municipal '
            . 'Agriculture Office.'
        );
    }

    /**
     * Tell the farmers' association, not the office (revision step 6).
     *
     * This used to notify all MAO, because the application used to go
     * straight into the office's queue. The association reads it first now,
     * so the association is who needs telling: the office cannot act on this
     * application until it has been forwarded, and an inbox full of items
     * nobody there can act on is an inbox people stop opening.
     *
     * The office is told when it is forwarded instead, by
     * Association\MembershipApplicationController::forward().
     *
     * 'specific_association' reaches that association's officer accounts
     * only, never its member farmers (see
     * NotificationBroadcast::recipientIds()): the other members have no
     * business being told by name who has applied to join them.
     *
     * In-app only ('normal' priority): a new application is routine, and
     * section 68 reserves SMS for urgent and critical matters. Runs after
     * the transaction has committed, and never throws, because a
     * notification failure must never be the reason a farmer's registration
     * appears to fail.
     */
    private function notifyAssociationOfNewApplication(Farmer $farmer): void
    {
        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'New Membership Application',
                'message'     => $farmer->full_name . ' has applied to join your association and is '
                    . 'waiting for your review. Once you have confirmed they are one of your members, '
                    . 'forward the application to the Municipal Agriculture Office.',
                'category'    => 'system',
                'priority'    => 'normal',
                'target_type' => 'specific_association',
                'target_id'   => $farmer->association_id,
                'status'      => 'draft',
                'created_by'  => $farmer->user_id,
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify association ' . $farmer->association_id
                . ' of new application for farmer ' . $farmer->id . ': ' . $e->getMessage());
        }
    }
}
