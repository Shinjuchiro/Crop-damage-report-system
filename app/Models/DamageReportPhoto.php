<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageReportPhoto extends Model
{
    protected $table = 'damage_report_photos';
    public $timestamps = false;

    protected $fillable = ['damage_report_id', 'file_path', 'file_name', 'uploaded_by', 'uploaded_at'];

    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * An authorised URL for this photo (revision section 3.4, Oct 2026).
     *
     * The views used to build asset('storage/' . $photo->file_path)
     * themselves, which pointed at a file the web server handed out with no
     * login at all. They ask for $photo->url now and
     * AttachmentController::damagePhoto() decides who gets it: this
     * farmer, an officer of their association, the technician handling the
     * case, or the office.
     */
    public function getUrlAttribute(): string
    {
        return route('attachments.damage-photo', $this);
    }
}
