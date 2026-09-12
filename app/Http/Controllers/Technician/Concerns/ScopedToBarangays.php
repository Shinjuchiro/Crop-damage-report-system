<?php

namespace App\Http\Controllers\Technician\Concerns;

use App\Models\Barangay;
use App\Models\DamageReport;
use Illuminate\Support\Facades\Auth;

/**
 * Barangays this technician actually has work in.
 *
 * "Worked in" means the office has, at some point, assigned them a damage
 * report with a reported barangay - not only reports open right now. The
 * same rule AssignmentController already uses for its own barangay filter,
 * pulled out here so Planting Reports and Damage Reports can share it
 * without copy-pasting the subquery a third time.
 *
 * A dropdown of all 41 barangays of Tanza would be noise on a phone, and
 * showing every barangay's activity to every technician would defeat the
 * point of scoping this to "their" territory at all.
 */
trait ScopedToBarangays
{
    protected function myBarangayIds()
    {
        return DamageReport::where('assigned_technician_id', Auth::id())
            ->whereNotNull('reported_barangay_id')
            ->distinct()
            ->pluck('reported_barangay_id');
    }

    protected function myBarangays()
    {
        return Barangay::whereIn('id', $this->myBarangayIds())
            ->orderBy('name')
            ->get();
    }
}
