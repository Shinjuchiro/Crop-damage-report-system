<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MaoAdminSeeder extends Seeder
{
    /**
     * Creates the ONE initial MAO / Super Admin account.
     * There is no public "Register as MAO" route - this is the only way
     * an MAO account ever gets created, per the spec.
     *
     * IMPORTANT: change this password immediately after first login.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'mao@tanza.gov.ph'],
            [
                'username' => 'mao_admin',
                'password' => Hash::make('MAOTanza123!'), // CHANGE THIS after first login
                'phone_number' => '09000000000',
                'status' => 'active',
                'preferred_language' => 'en',
            ]
        );

        // `role` is not mass assignable (see User), so it cannot ride along in
        // the array above and has to be set here. Without this the one account
        // that administers the whole system would be created with no role at
        // all and could not reach a single MAO page.
        if ($user->role !== 'mao') {
            $user->role = 'mao';
            $user->save();
        }
    }
}
