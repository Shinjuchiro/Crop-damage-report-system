<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The system's one audit trail (proposal section 81).
 *
 * WHAT THE EXTRA COLUMNS ARE FOR (added Oct 2026, migration 2024_01_23_000001)
 *
 * `action` was the whole record: one human-readable sentence, with any
 * remarks glued onto the end of it. That is fine to read and useless to
 * query, and it had a sharp edge - `action` was VARCHAR(255) and the
 * membership workflow appended notes of up to 1000 characters to it, so a
 * long correction note raised "Data too long for column 'action'" inside a
 * transaction. See the migration for the full account.
 *
 * So a transition now records what it moved FROM and TO as data, and its
 * remarks in their own column:
 *
 *     from_status / to_status   the two ends of the move
 *     remarks                   what the person typed, verbatim
 *
 * All three are nullable and most rows in this table have none of them: an
 * ordinary "Archived assistance item" entry is not a transition and has
 * nothing to put there. Only the workflows that have stages fill them in.
 *
 * This is deliberately not a second history table. The revision that asked
 * for application history said to reuse the existing audit log if there is
 * one, and there is.
 */
class AuditLog extends Model
{
    protected $table = 'audit_logs';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'from_status', 'to_status', 'remarks',
        'target_table', 'target_id', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Every entry about one record, newest first.
     *
     * Covered by the (target_table, target_id) index the same migration
     * adds, so this stays a short index lookup rather than a scan of a table
     * that only ever grows.
     */
    public function scopeAbout($query, string $table, int $id)
    {
        return $query->where('target_table', $table)
            ->where('target_id', $id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
