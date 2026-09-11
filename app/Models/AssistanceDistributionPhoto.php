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
}
