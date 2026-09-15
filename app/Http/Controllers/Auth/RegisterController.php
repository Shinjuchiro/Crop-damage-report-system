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

    public function store(FarmerRegistrationRequest $request)
    {
        $data = $request->validated();

        // Store the barangay certificate first (outside the transaction, since
        // file writes are not rolled back by the database anyway)
        $certificatePath = null;
        if ($request->hasFile('barangay_certificate')) {
            $certificatePath = $request->file('barangay_certificate')
                ->store('barangay-certificates', 'public');
        }

        $farmer = DB::transaction(function () use ($data, $certificatePath) {
            $user = User::create([
                'username'           => $data['username'],
                'email'              => $data['email'],
                'password'           => $data['password'], // auto-hashed by the model cast
                'phone_number'       => $data['phone_number'],
                'role'               => 'farmer',
                'status'             => 'pending', // MAO must approve before full access
                'preferred_language' => 'en',
            ]);

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
                'activity_status'           => 'active',
                'last_activity_date'        => now()->toDateString(),
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

        $this->notifyMaoOfNewRegistration($farmer);

        return redirect()->route('login')->with('status',
            'Registration submitted successfully. Your account is pending verification by the Municipal Agriculture Office.'
        );
    }

    /**
     * Proposal section 66 lists "New registration" as one of the things MAO
     * should be notified about. This was previously missing entirely - a
     * farmer could submit a registration and it would just sit in the
     * Membership Applications queue with nobody told it was there.
     *
     * In-app only ('normal' priority, see NotificationBroadcast::PRIORITIES):
     * a new registration is routine, not urgent, and SMS is reserved for
     * urgent/critical matters (section 68). Runs after the transaction above
     * has already committed, and never throws - a notification failure must
     * never be the reason a farmer's registration appears to fail.
     */
    private function notifyMaoOfNewRegistration(Farmer $farmer): void
    {
        try {
            $alert = NotificationBroadcast::create([
                'title'       => 'New Farmer Registration',
                'message'     => $farmer->full_name . ' has submitted a farmer registration and is waiting for review.',
                'category'    => 'system',
                'priority'    => 'normal',
                'target_type' => 'all_mao',
                'status'      => 'draft',
                'created_by'  => $farmer->user_id,
            ]);

            $alert->dispatchToRecipients();
        } catch (\Throwable $e) {
            Log::warning('Could not notify MAO of new registration for farmer ' . $farmer->id . ': ' . $e->getMessage());
        }
    }
}
