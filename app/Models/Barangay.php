<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barangay extends Model
{
    protected $table = 'barangays';
    public $timestamps = false;

    protected $fillable = ['name', 'psgc_code'];

    public function farmers()
    {
        return $this->hasMany(Farmer::class);
    }
}
