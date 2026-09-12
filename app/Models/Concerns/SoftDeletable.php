<?php

namespace App\Models\Concerns;

use App\Models\User;

/**
 * "Delete Permanently" on the Archive page (proposal section 91.10) is never
 * a real SQL DELETE. Every field on the row stays in the database exactly as
 * it was - a report or record that already points at it keeps resolving it,
 * same reasoning as Archivable. All that changes is:
 *
 *   1. It stops appearing in the ordinary lists this record type already had
 *      (its management page, the Archive page's Archived tab, dashboards,
 *      directories) - scopeNotDeleted() is what those queries opt into.
 *   2. It shows up instead on the Archive page's Deleted tab
 *      (scopeOnlyDeleted()), read-only, together with who deleted it
 *      (deletedBy()) and when (deleted_at).
 *
 * Used by Association, Crop, Disaster (alongside Archivable - see that
 * trait's scopeActive()/scopeOnlyArchived(), which were updated to also
 * exclude deleted rows) and by Farmer, User, Assistance, NotificationBroadcast
 * (which have no archived_at of their own; their existing status column
 * still means "archived", deleted_at now means something stronger than
 * that).
 *
 * Deliberately not Eloquent's own SoftDeletes trait: that trait adds a
 * GLOBAL scope, which would also hide a deleted record from every relation
 * that already points at it (an old damage report's own $report->farmer
 * would come back null the moment that farmer is deleted) - exactly the
 * silent breakage Archivable's own docblock warns against. So, like
 * Archivable, this only adds scopes that callers opt into on purpose.
 */
trait SoftDeletable
{
    public function scopeNotDeleted($query)
    {
        return $query->whereNull($this->getTable() . '.deleted_at');
    }

    public function scopeOnlyDeleted($query)
    {
        return $query->whereNotNull($this->getTable() . '.deleted_at');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function getIsDeletedAttribute(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * Move this record out of the working system for good, without erasing
     * anything. Every other field keeps its value.
     */
    public function markDeleted(int $byUserId): void
    {
        $this->forceFill([
            'deleted_at' => now(),
            'deleted_by' => $byUserId,
        ])->save();
    }
}
