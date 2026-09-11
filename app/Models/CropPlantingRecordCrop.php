<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CropPlantingRecordCrop extends Model
{
    protected $table = 'crop_planting_record_crops';
    public $timestamps = false;

    protected $fillable = ['crop_planting_record_id', 'crop_id', 'crop_specify', 'date_planted', 'area_hectares'];

    protected function casts(): array
    {
        return ['date_planted' => 'date'];
    }

    public function plantingRecord()
    {
        return $this->belongsTo(CropPlantingRecord::class, 'crop_planting_record_id');
    }

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }
}
