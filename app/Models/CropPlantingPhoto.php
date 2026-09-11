<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CropPlantingPhoto extends Model
{
    protected $table = 'crop_planting_photos';
    public $timestamps = false;

    protected $fillable = ['crop_planting_record_id', 'file_path', 'uploaded_at'];

    public function plantingRecord()
    {
        return $this->belongsTo(CropPlantingRecord::class, 'crop_planting_record_id');
    }
}
