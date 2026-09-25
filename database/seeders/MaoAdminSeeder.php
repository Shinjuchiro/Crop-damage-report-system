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
        if (User::where('email', 'mao@tanza.gov.ph')->exists()) {
            return;
        }

        $user = new User([
            'username' => 'mao_admin',
            'email' => 'mao@tanza.gov.ph',
            'password' => Hash::make('MAOTanza123!'), // CHANGE THIS after first login
            'phone_number' => '09000000000',
            'status' => 'active',
            'preferred_language' => 'en',
        ]);

        // `role` is not mass assignable (see User), so it cannot ride along in
        // the array above. It has to be set before save(), not after: the
        // column is a NOT NULL enum with no default, so an INSERT that leaves
        // it out fails outright and this account never gets created.
        $user->role = 'mao';

        $user->save();
    }
}
