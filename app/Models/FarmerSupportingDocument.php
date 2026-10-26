<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One file a tenant uploaded with their registration: a tenancy agreement,
 * an authorization letter from the land owner, or similar.
 *
 * Read only through AttachmentController::supportingDocument(); the URL
 * accessor below is the one way a view links to it.
 */
class FarmerSupportingDocument extends Model
{
    protected $fillable = ['farmer_id', 'file_path', 'file_name', 'mime_type', 'file_size'];

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function getUrlAttribute(): string
    {
        return route('attachments.supporting-document', $this);
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function getIsPdfAttribute(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /** "1.4 MB" or "230 KB", for the list under a document's name. */
    public function getSizeLabelAttribute(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes <= 0) {
            return '';
        }

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1) . ' MB'
            : number_format(max(1, round($bytes / 1024))) . ' KB';
    }
}
