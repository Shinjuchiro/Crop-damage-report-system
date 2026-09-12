<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Concerns\ManagesAccountSettings;
use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    use ManagesAccountSettings;

    protected function settingsView(string $page): string
    {
        return "technician.settings.$page";
    }
}
