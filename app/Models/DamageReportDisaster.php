<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageReportDisaster extends Model
{
    protected $table = 'damage_report_disasters';
    public $timestamps = false;

    protected $fillable = ['damage_report_id', 'disaster_id'];

    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class);
    }

    public function disaster()
    {
        return $this->belongsTo(Disaster::class);
    }
}
