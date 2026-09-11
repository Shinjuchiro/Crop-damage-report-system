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
        User::firstOrCreate(
            ['email' => 'mao@tanza.gov.ph'],
            [
                'username' => 'mao_admin',
                'password' => Hash::make('ChangeMe123!'), // CHANGE THIS after first login
                'phone_number' => '09000000000',
                'role' => 'mao',
                'status' => 'active',
                'preferred_language' => 'en',
            ]
        );
    }
}
