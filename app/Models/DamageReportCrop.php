<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageReportCrop extends Model
{
    protected $table = 'damage_report_crops';
    public $timestamps = false;

    protected $fillable = [
        'damage_report_id', 'crop_id', 'crop_specify', 'damaged_area_hectares',
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
}
