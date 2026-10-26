<?php

namespace App\Http\Controllers;

use App\Models\AssistanceAllocationDocument;
use App\Models\AssistanceDistributionPhoto;
use App\Models\AssociationOfficer;
use App\Models\CropPlantingPhoto;
use App\Models\DamageReportPhoto;
use App\Models\Farmer;
use App\Models\FarmerSupportingDocument;
use App\Models\ValidationPhoto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * THE ONLY WAY TO READ AN UPLOADED FILE (built Oct 2026)
 *
 * Revision spec 3.4: "Do not expose sensitive information through public
 * URLs", "Restrict attachments and supporting documents to authorized
 * users", and the line underneath them that reads as though it were written
 * about exactly the state this system was in: "Progressive Disclosure is a
 * user-interface principle, not a complete security mechanism. Hiding
 * details in a table does not protect information if the backend still
 * exposes it."
 *
 * WHAT IT WAS BEFORE
 *
 * Every upload in this system went to Laravel's "public" disk, and
 * public/storage is a symlink into it, so every file was served by the web
 * server directly with no session, no role and no check of any kind. A
 * barangay certificate carries a farmer's full name and home address. A
 * handover photo shows a named farmer receiving assistance. Both were
 * readable by anybody who had the URL, signed in or not, forever - a link
 * pasted into a chat, left in a browser history, or still open in a tab
 * belonging to a farmer the office has since rejected.
 *
 * The filenames are forty random characters, so nobody was going to guess
 * one. That is worth saying honestly rather than overstating the problem.
 * But obscurity was the whole of the protection, and the spec asks for
 * authorisation.
 *
 * WHAT IT IS NOW
 *
 * Uploads go to the private disk ("local", which is storage/app/private and
 * is not reachable from the web at all), and the only route to their bytes
 * is this controller. Each method re-asks the question the PAGE already
 * asks: may this signed-in person see this farmer's record? So a file is
 * exactly as restricted as the screen it appears on, which is what section
 * 19.2 means by "attachments and exports follow the same permission rules".
 *
 * WHY ONE CONTROLLER AND NOT A POLICY PER MODEL
 *
 * Because every one of these files is really a file ABOUT A FARMER, and the
 * answer is the same sentence in every case: the farmer themselves, an
 * officer of their association, the technician handling the case, or the
 * office. assertMaySeeFarmersFile() is that sentence written once. Six
 * policy classes would have been six copies of it.
 *
 * NOT BEHIND 'active'
 *
 * Deliberately. These routes sit in the plain 'auth' group, because a farmer
 * correcting a returned application is still 'pending', and that screen
 * shows them the certificate they already uploaded. Authorisation here is
 * per file, not per role, so the role middleware would add nothing anyway.
 *
 * PROFILE PHOTOS ARE NOT HERE
 *
 * They stay on the public disk, and that is a decision rather than an
 * oversight. An avatar renders in the top bar of every page for every role,
 * so routing each one through a controller would add a query and a PHP
 * request per page render, and a face a farmer chose to upload as their own
 * profile picture is not in the same class as a government document bearing
 * their address.
 */
class AttachmentController extends Controller
{
    /**
     * The farmer's barangay certificate.
     *
     * Bound to the Farmer rather than to a file path, so the id in the URL
     * is a farmer id the guard can reason about. No technician ever sees a
     * certificate on any screen, so none is allowed one here.
     */
    public function barangayCertificate(Farmer $farmer)
    {
        $this->assertMaySeeFarmersFile($farmer);

        return $this->serve($farmer->barangay_certificate_path);
    }

    /**
     * A supporting document a tenant uploaded with their registration: the
     * tenancy agreement, an authorization letter, and similar.
     *
     * Same readers as the barangay certificate (the farmer, their own
     * association's officers, the office) and for the same reason: it is a
     * paper about a named person's land. No technician is ever listed.
     * Images and PDFs are sent inline, so the link opens them in the browser's
     * own viewer in a new tab and the page the reviewer was on is untouched.
     */
    public function supportingDocument(FarmerSupportingDocument $document)
    {
        $this->assertMaySeeFarmersFile($document->farmer);

        return $this->serve($document->file_path, $document->file_name);
    }

    /**
     * A photo the FARMER uploaded with their damage report.
     *
     * Two technicians may hold it: whoever is assigned now, and whoever
     * recorded the inspection. The second matters because
     * Technician\AssignmentController::historyShow() lets a technician
     * reopen an inspection they finished, and that page renders these
     * photos, so a reassignment must not blank out their own history.
     */
    public function damagePhoto(DamageReportPhoto $photo)
    {
        $report = $photo->damageReport;

        abort_if($report === null, 404);

        $this->assertMaySeeFarmersFile($report->farmer, [
            $report->assigned_technician_id,
            $report->validation?->technician_id,
        ]);

        return $this->serve($photo->file_path, $photo->file_name);
    }

    /**
     * A photo the TECHNICIAN took during the field inspection. Separate
     * table, separate route, same readers (section 36 keeps the two sets of
     * evidence apart; it does not give them different audiences).
     */
    public function inspectionPhoto(ValidationPhoto $photo)
    {
        $validation = $photo->validation;
        $report     = $validation?->damageReport;

        abort_if($validation === null || $report === null, 404);

        $this->assertMaySeeFarmersFile($report->farmer, [
            $validation->technician_id,
            $report->assigned_technician_id,
        ]);

        return $this->serve($photo->file_path);
    }

    /**
     * A crop planting record's photo.
     *
     * No technician is listed: the technician's Planting Reports page is
     * barangay-wide and shows counts and figures, never the images, so
     * granting the whole barangay's technicians access to a farmer's photos
     * would hand out more than any screen asks for.
     */
    public function plantingPhoto(CropPlantingPhoto $photo)
    {
        $record = $photo->plantingRecord;

        abort_if($record === null, 404);

        $this->assertMaySeeFarmersFile($record->farmer);

        return $this->serve($photo->file_path);
    }

    /**
     * An endorsement memo or beneficiary list attached while allocating.
     *
     * Office only, which is the one file here that is not about a single
     * farmer: it is an internal document, shown on the MAO allocation detail
     * and nowhere else. An officer seeing a batch that happens to include
     * one of their members has no claim on the office's own paperwork.
     */
    public function allocationDocument(AssistanceAllocationDocument $document)
    {
        abort_unless(Auth::user()?->role === 'mao', 403);

        return $this->serve($document->file_path, $document->file_name);
    }

    /**
     * Proof of a handover: the farmer receiving what the office brought.
     *
     * Nothing renders these yet, which is precisely why the guard is written
     * now rather than when a screen for them appears. The readers are the
     * readers of the distribution record itself.
     */
    public function distributionPhoto(AssistanceDistributionPhoto $photo)
    {
        $distribution = $photo->distribution;

        abort_if($distribution === null, 404);

        $this->assertMaySeeFarmersFile($distribution->farmer);

        return $this->serve($photo->file_path);
    }

    /**
     * May the signed-in user see a file belonging to this farmer?
     *
     * The whole authorisation model for attachments, in one place:
     *
     *   MAO          every farmer, office-wide, as everywhere else
     *   the farmer   their own files and nobody else's
     *   an officer   the files of farmers in their own association
     *   a technician only the technicians named by the caller, which is
     *                always "whoever is actually handling this case"
     *
     * Anyone else gets a 403, including a farmer who edits the id in the
     * URL, which is the attack section 3.4 names outright: "Prevent users
     * from viewing another person's records by changing an ID or URL."
     *
     * @param  array<int, int|null>  $technicianIds
     */
    private function assertMaySeeFarmersFile(?Farmer $farmer, array $technicianIds = []): void
    {
        // A file whose owning farmer has been hard-removed is a 404, not a
        // 403: there is no record left to be authorised against.
        abort_if($farmer === null, 404);

        $user = Auth::user();

        abort_if($user === null, 403);

        if ($user->role === 'mao') {
            return;
        }

        if ($user->role === 'farmer' && (int) $farmer->user_id === (int) $user->id) {
            return;
        }

        /* The null check is not redundant. A farmer can have no association,
           and without it a NULL association_id on the farmer compared
           against a NULL lookup for an officer with no association row would
           come out equal and let them through. */
        if ($user->role === 'association' && $farmer->association_id !== null) {
            $officerAssociationId = AssociationOfficer::where('user_id', $user->id)
                ->value('association_id');

            if ($officerAssociationId !== null
                && (int) $officerAssociationId === (int) $farmer->association_id) {
                return;
            }
        }

        if ($user->role === 'technician') {
            $allowed = array_filter(array_map('intval', array_filter($technicianIds)));

            if (in_array((int) $user->id, $allowed, true)) {
                return;
            }
        }

        abort(403);
    }

    /**
     * Hand over the bytes, once somebody is allowed to have them.
     *
     * THE PUBLIC DISK IS STILL CHECKED, ON PURPOSE
     *
     * New uploads land on the private disk. Files uploaded before this
     * change are still sitting on the public one until
     * `php artisan attachments:privatise` moves them, and a deployment where
     * the code is live for ten minutes before anybody runs a command is the
     * normal case, not an unusual one. Checking both means those ten minutes
     * do not show the office a page full of broken images.
     *
     * The fallback is a read, never a write, and it is safe to delete from
     * this method once the command has run on every environment. Leaving it
     * in costs one exists() call on a disk that will be empty.
     *
     * THE CACHE HEADER, AND WHY IT IS NOT A SHORT max-age (fixed Oct 2026)
     *
     * This sent 'private, max-age=300'. The reasoning was that a damage
     * report page shows a dozen thumbnails and re-fetching them on every
     * scroll would make the office's own screens slow, and 'private' keeps
     * any shared cache or CDN out of it.
     *
     * Testing it live showed what that actually buys an attacker. Signed in
     * as the office, open a damage photo; sign out; open the same URL again
     * - and the photo is still there, served by the browser from its own
     * disk cache, for five more minutes. The route is doing its job (with
     * the cache bypassed it redirects to the login page, which was verified
     * the same way) and it does not matter, because the request never
     * reaches the route.
     *
     * The Municipal Agriculture Office is exactly where that bites: one
     * machine, several staff, people signing in and out of it all day, and
     * the file sitting in the cache is a farmer's barangay certificate with
     * their home address on it.
     *
     * So: 'no-store, no-cache, must-revalidate, max-age=0'. The browser
     * keeps no copy and every view of every attachment goes through
     * assertMaySeeFarmersFile() again. That is the same header Laravel's own
     * private-file server sends (Illuminate\Filesystem\ServeFile), which is
     * a reasonable thing to agree with about private files.
     *
     * It costs a re-download when a page of thumbnails is revisited. Worth
     * it: the alternative is an authorisation check that a page reload can
     * skip.
     */
    private function serve(?string $path, ?string $downloadName = null)
    {
        abort_if($path === null || trim($path) === '', 404);

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path, $downloadName, [
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                ]);
            }
        }

        /* The row says there is a file and there is not one. A 404 rather
           than a 500: it is a missing file, which is a thing that happens,
           and the page around it should keep working. */
        abort(404);
    }
}
