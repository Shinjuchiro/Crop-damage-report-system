<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Model;

class Disaster extends Model
{
    use Archivable;

    protected $table = 'disasters';

    protected $fillable = ['type', 'name', 'date_start', 'date_end'];

    protected function casts(): array
    {
        return ['date_start' => 'date', 'date_end' => 'date'];
    }

    public function damageReports()
    {
        return $this->belongsToMany(DamageReport::class, 'damage_report_disasters');
    }
}
