<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Concerns\ManagesAccountSettings;
use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    use ManagesAccountSettings;

    protected function settingsView(string $page): string
    {
        return "association.settings.$page";
    }
}
