<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CropPlantingRecord extends Model
{
    protected $table = 'crop_planting_records';

    protected $fillable = ['farmer_id', 'date_submitted'];

    protected function casts(): array
    {
        return ['date_submitted' => 'date'];
    }

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function crops()
    {
        return $this->hasMany(CropPlantingRecordCrop::class);
    }

    public function photos()
    {
        return $this->hasMany(CropPlantingPhoto::class);
    }
}
