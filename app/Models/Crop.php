<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\SoftDeletable;
use Illuminate\Database\Eloquent\Model;

class Crop extends Model
{
    use Archivable, SoftDeletable;

    protected $table = 'crops';

    protected $fillable = ['name', 'is_hvcc'];

    protected function casts(): array
    {
        // archived_at was missing here before Sept 2026 - a plain DB string
        // rather than Carbon, which threw the moment anything called
        // ->format() on it (every archived crop shown on the Archive page).
        return ['is_hvcc' => 'boolean', 'archived_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    public function mainCrops()
    {
        return $this->hasMany(FarmerMainCrop::class);
    }

    public function damageReportCrops()
    {
        return $this->hasMany(DamageReportCrop::class);
    }

    public function plantingRecordCrops()
    {
        return $this->hasMany(CropPlantingRecordCrop::class);
    }
}
