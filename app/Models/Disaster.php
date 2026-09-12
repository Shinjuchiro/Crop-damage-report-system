<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\SoftDeletable;
use Illuminate\Database\Eloquent\Model;

class Disaster extends Model
{
    use Archivable, SoftDeletable;

    protected $table = 'disasters';

    protected $fillable = ['type', 'name', 'date_start', 'date_end'];

    protected function casts(): array
    {
        return ['date_start' => 'date', 'date_end' => 'date', 'deleted_at' => 'datetime'];
    }

    public function damageReports()
    {
        return $this->belongsToMany(DamageReport::class, 'damage_report_disasters');
    }
}
