<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Move every already-uploaded attachment off the public disk (Oct 2026).
 *
 * WHY THIS EXISTS
 *
 * The code change that put attachments behind App\Http\Controllers\
 * AttachmentController only affects files uploaded from now on. Everything
 * uploaded before it is still sitting in storage/app/public, still reachable
 * at /storage/<path> by anybody, with or without a login - which is the
 * whole thing revision section 3.4 asked us to stop. New code plus old files
 * is not a fix; it is a fix on half the data.
 *
 * So this walks the five attachment folders and moves each file to the same
 * relative path on the private disk. The database is untouched, because
 * file_path has always been stored relative to the disk root and the
 * relative path does not change. That is the reason this is a file move and
 * not a migration.
 *
 * RUN IT ONCE PER ENVIRONMENT
 *
 *     php artisan attachments:privatise --dry-run    (see what would move)
 *     php artisan attachments:privatise              (move them)
 *
 * It is safe to run twice. A second run finds the folders empty and reports
 * nothing to do.
 *
 * PROFILE PHOTOS ARE DELIBERATELY NOT IN THE LIST
 *
 * They stay public. App\Models\User::getProfilePhotoUrlAttribute() still
 * points at /storage, an avatar renders on every page for every role, and a
 * profile picture somebody chose for themselves is not a government document
 * carrying their address. AttachmentController's docblock has the longer
 * version of this reasoning.
 */
class PrivatiseAttachments extends Command
{
    protected $signature = 'attachments:privatise {--dry-run : List what would move without moving anything}';

    protected $description = 'Move existing uploads from the public disk to the private disk (revision section 3.4)';

    /**
     * The folders that hold attachments, as file_path records them.
     *
     * These are exactly the five prefixes the upload sites write to:
     * Auth\RegisterController and Farmer\ApplicationController
     * (barangay-certificates), Farmer\DamageReportController
     * (damage-reports), Technician\InspectionController (inspections),
     * MAO\AssistanceAllocationController (the other two). If a sixth kind of
     * upload is ever added, it goes straight to the private disk and never
     * needs to appear here.
     */
    private const FOLDERS = [
        'barangay-certificates',
        'damage-reports',
        'inspections',
        'distributions',
        'assistance-allocation-documents',
    ];

    public function handle(): int
    {
        $public  = Storage::disk('public');
        $private = Storage::disk('local');
        $dryRun  = (bool) $this->option('dry-run');

        $moved   = 0;
        $skipped = 0;
        $failed  = 0;

        foreach (self::FOLDERS as $folder) {
            if (! $public->exists($folder)) {
                continue;
            }

            // allFiles() recurses, which matters: damage-reports, inspections
            // and distributions each keep one subfolder per record id.
            foreach ($public->allFiles($folder) as $path) {
                /* Already there. Can happen if a previous run was
                   interrupted, or if somebody uploaded a replacement after
                   the code change but before this command. The private copy
                   is the newer one, so it wins and the public one is left
                   alone rather than overwritten. */
                if ($private->exists($path)) {
                    $this->line("  <comment>skip</comment>  {$path} (already private)");
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("  <info>would move</info>  {$path}");
                    $moved++;

                    continue;
                }

                /* Read, write, verify, then delete - rather than a rename -
                   because the two disks are not guaranteed to be on the same
                   filesystem, and on a host where they are not, a rename
                   fails.

                   THE SIZE CHECK IS NOT BELT AND BRACES

                   config/filesystems.php sets 'throw' => false on both
                   disks, so a file the process cannot read comes back as
                   false rather than raising. Without this check that false
                   would be written as an empty file, exists() would happily
                   confirm it, and the original would then be deleted: a
                   silent, unrecoverable loss of a farmer's certificate. So
                   the only thing allowed to trigger the delete is a private
                   copy that exists AND is the same number of bytes as the
                   original. */
                try {
                    $expected = $public->size($path);
                    $contents = $public->get($path);

                    if ($contents === null || $contents === false) {
                        throw new \RuntimeException('could not read the file from the public disk');
                    }

                    $private->put($path, $contents);

                    if (! $private->exists($path)) {
                        throw new \RuntimeException('the private copy was not written');
                    }

                    if ($private->size($path) !== $expected) {
                        throw new \RuntimeException(
                            'the private copy is ' . $private->size($path) . ' bytes, the original is '
                            . $expected . ' - the original has been left alone'
                        );
                    }

                    $public->delete($path);

                    $this->line("  <info>moved</info>  {$path}");
                    $moved++;
                } catch (\Throwable $e) {
                    $this->line("  <error>failed</error>  {$path}: " . $e->getMessage());
                    $failed++;
                }
            }
        }

        $this->newLine();

        if ($moved === 0 && $skipped === 0 && $failed === 0) {
            $this->info('Nothing to move. Every attachment is already on the private disk.');

            return self::SUCCESS;
        }

        $this->info($dryRun
            ? "{$moved} file(s) would move, {$skipped} already private."
            : "{$moved} file(s) moved, {$skipped} already private, {$failed} failed.");

        if (! $dryRun && $failed === 0 && $moved > 0) {
            $this->newLine();
            $this->line('The attachment folders under public/storage are now empty. Nothing in the');
            $this->line('application reads them, so they can be removed by hand if you want the');
            $this->line('directory tidy. Leave public/storage/profile-photos alone: avatars still');
            $this->line('live there and are still meant to be public.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
