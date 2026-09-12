<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FarmerMainCrop extends Model
{
    protected $table = 'farmer_main_crops';
    public $timestamps = false;

    protected $fillable = ['farmer_id', 'crop_id', 'crop_specify'];

    public function farmer(){
        return $this->belongsTo(Farmer::class);
    }

    public function crop(){
        return $this->belongsTo(Crop::class);
    }
}
