<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A supporting file attached while allocating - an MAO endorsement memo, a
 * beneficiary list, or similar.
 *
 * MOVED OFF THE PUBLIC DISK (Oct 2026, revision section 3.4)
 *
 * This docblock used to say the file was "stored on the public disk like
 * every other upload in this system, so it is served straight from /storage
 * without a dedicated download route", and it was accurate. That is exactly
 * what the privacy sweep came to fix: no login was needed to read any of it.
 * There is a download route now, it is office-only, and the file lives on
 * the private disk. See App\Http\Controllers\AttachmentController.
 */
class AssistanceAllocationDocument extends Model
{
    protected $table = 'assistance_allocation_documents';
    public $timestamps = false;

    protected $fillable = [
        'assistance_allocation_id', 'file_path', 'file_name', 'file_size', 'mime_type',
        'uploaded_by', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function allocation()
    {
        return $this->belongsTo(AssistanceAllocation::class, 'assistance_allocation_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Unchanged on the outside: both views still read $document->url. What
     * changed is where it points. It was a direct /storage path the web
     * server served to anyone; it is now a route only the office can follow.
     */
    public function getUrlAttribute(): string
    {
        return route('attachments.allocation-document', $this);
    }

    /** A readable size, e.g. "2.4 MB", instead of a raw byte count. */
    public function getSizeLabelAttribute(): string
    {
        if (! $this->file_size) {
            return '';
        }

        $kb = $this->file_size / 1024;

        return $kb < 1024
            ? number_format($kb, 0) . ' KB'
            : number_format($kb / 1024, 1) . ' MB';
    }
}
