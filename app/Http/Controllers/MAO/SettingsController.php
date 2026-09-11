<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;
use App\Models\Assistance;
use App\Models\Association;
use App\Models\Barangay;
use App\Models\Crop;
use App\Models\Disaster;

/**
 * Hub for the reference data the rest of the system depends on.
 */
class SettingsController extends Controller
{
    public function index()
    {
        return view('mao.settings.index', [
            'counts' => [
                'associations' => Association::count(),
                'assistance'   => Assistance::count(),
                'crops'        => Crop::count(),
                'disasters'    => Disaster::count(),
                'barangays'    => Barangay::count(),
            ],
        ]);
    }
}
