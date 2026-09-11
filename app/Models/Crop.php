<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Model;

class Crop extends Model
{
    use Archivable;

    protected $table = 'crops';

    protected $fillable = ['name', 'is_hvcc'];

    protected function casts(): array
    {
        return ['is_hvcc' => 'boolean'];
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
