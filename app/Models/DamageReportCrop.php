<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageReportCrop extends Model
{
    protected $table = 'damage_report_crops';
    public $timestamps = false;

    protected $fillable = [
        'damage_report_id', 'crop_id', 'crop_specify', 'crop_planting_record_crop_id', 'damaged_area_hectares',
        'date_planted', 'estimated_damage_percent', 'production_cost',
        'total_damage_cost', 'farmgate_price_per_kg',
    ];

    protected function casts(): array
    {
        return ['date_planted' => 'date'];
    }

    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class);
    }

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }

    /** The Crop Planted record the farmer picked, when they picked one (null: date typed by hand). */
    public function plantingRecordCrop()
    {
        return $this->belongsTo(CropPlantingRecordCrop::class, 'crop_planting_record_crop_id');
    }
}
