<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A supporting file attached while allocating - an MAO endorsement memo, a
 * beneficiary list, or similar. Stored on the "public" disk like every other
 * upload in this system, so it is served straight from /storage without a
 * dedicated download route.
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

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
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
