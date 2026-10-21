<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistanceDistributionPhoto extends Model
{
    protected $table = 'assistance_distribution_photos';
    public $timestamps = false;

    protected $fillable = ['assistance_distribution_id', 'photo_type', 'file_path', 'uploaded_at'];

    public function distribution()
    {
        return $this->belongsTo(AssistanceDistribution::class, 'assistance_distribution_id');
    }

    /**
     * An authorised URL for this photo (revision section 3.4, Oct 2026).
     *
     * No screen renders handover photos yet. The accessor and its guard are
     * written now anyway, so that whenever one is added the link is already
     * the authorised one and nobody has to remember this rule a second time.
     * See AttachmentController::distributionPhoto().
     */
    public function getUrlAttribute(): string
    {
        return route('attachments.distribution-photo', $this);
    }
}
