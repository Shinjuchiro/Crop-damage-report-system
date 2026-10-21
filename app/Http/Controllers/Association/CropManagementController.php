<?php

namespace App\Http\Controllers\Association;

use App\Http\Controllers\Controller;

/**
 * Crop Management Report, the association's side.
 *
 * WHAT HAPPENED TO THE HUB
 *
 * This used to be a page of two cards, each a door to one of the two record
 * screens, with the figures printed on the cards. It now works the way the
 * office's does: the two screens carry a tab strip across the top and read
 * as one report with two tabs, and each screen shows its own figures above
 * the strip. See resources/views/components/crop-management/header.blade.php
 * and App\Support\CropManagement::tabs('association').
 *
 * The route is kept rather than deleted, for the same reasons the office's
 * is (see MAO\CropManagementController): the sidebar, the mobile bar and
 * any bookmark an officer has made already point at
 * association.crop-management.index, and a redirect keeps every one of them
 * working. The name is also the one the menu uses, so it is the honest home
 * for the section even when its first tab is what appears.
 *
 * Crop Planted is first because that is where the work starts: a member
 * plants, then reports damage.
 *
 * The figures that used to be on the cards are not lost. Each page now
 * builds its own, scoped to this officer's members by ResolvesAssociation
 * exactly as before, in Association\MonitoringController.
 */
class CropManagementController extends Controller
{
    public function index()
    {
        return redirect()->route('association.planting.index');
    }
}
