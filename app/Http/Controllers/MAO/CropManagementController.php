<?php

namespace App\Http\Controllers\MAO;

use App\Http\Controllers\Controller;

/**
 * Crop Management Report.
 *
 * WHAT HAPPENED TO THE HUB
 *
 * This used to be a page of three cards, each a door to one of the three
 * record screens. The cards are still there, but they are now a strip across
 * the top of the screens themselves, with a tab bar under them: see
 * resources/views/components/crop-management/header.blade.php and
 * App\Support\CropManagement. Crop Planted, Crop Damage and Validation
 * Monitoring read as one screen with three tabs rather than three pages
 * behind a landing page, so moving between them is one click instead of
 * three.
 *
 * That leaves this route with nothing to render, and it is kept rather than
 * deleted for two reasons. The sidebar, the mobile bar and a scattering of
 * links already point at mao.crop-management.index, and so do any bookmarks
 * the office has made; a redirect keeps every one of them working. And the
 * name is the one the menu uses, so it is the honest home for the section
 * even when the section's first tab is what actually appears.
 *
 * Crop Planted is the first tab because it is where the work starts: a
 * farmer plants, then reports damage, then a technician verifies it.
 */
class CropManagementController extends Controller
{
    public function index()
    {
        return redirect()->route('mao.crop-planting.index');
    }
}
