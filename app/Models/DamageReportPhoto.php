<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageReportPhoto extends Model
{
    protected $table = 'damage_report_photos';
    public $timestamps = false;

    protected $fillable = ['damage_report_id', 'file_path', 'file_name', 'uploaded_by', 'uploaded_at'];

    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
