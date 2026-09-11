<?php

namespace App\Models\Concerns;

use App\Models\User;

/**
 * Section 72: "Archive / Deactivate instead of permanent deletion whenever
 * appropriate."
 *
 * Used by Association, Crop and Disaster. Deliberately NOT a global scope:
 * a global scope would also hide an archived crop from every relationship
 * that already points at it (a five year old damage report's
 * $report->crops->first()->crop would come back null the moment that crop is
 * archived), which would quietly break historical records. Archiving must
 * only ever affect where a record can be picked FROM, never a record that
 * already exists.
 *
 * So this trait only adds query scopes, which callers opt into on purpose:
 *
 *   Crop::active()->get()        the picker on a form (do not offer archived
 *                                 items as a fresh choice)
 *   Crop::onlyArchived()->get()  the Archive page
 *   Crop::query()->get()         everything else, unchanged, archived items
 *                                 included, because a report already citing
 *                                 one still needs to display its name
 */
trait Archivable
{
    public function scopeActive($query)
    {
        return $query->whereNull($this->getTable() . '.archived_at');
    }

    public function scopeOnlyArchived($query)
    {
        return $query->whereNotNull($this->getTable() . '.archived_at');
    }

    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Take this record out of circulation. It keeps every relationship it
     * already has; it just stops being offered as a choice for new ones.
     */
    public function archive(int $byUserId): void
    {
        $this->forceFill([
            'archived_at' => now(),
            'archived_by' => $byUserId,
        ])->save();
    }

    /**
     * Put it back into circulation.
     */
    public function unarchive(): void
    {
        $this->forceFill([
            'archived_at' => null,
            'archived_by' => null,
        ])->save();
    }
}
