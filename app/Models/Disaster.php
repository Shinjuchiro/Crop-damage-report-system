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
        // archived_at cast added Sept 2026 - see Crop::casts() for why.
        return ['date_start' => 'date', 'date_end' => 'date', 'archived_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    public function damageReports()
    {
        return $this->belongsToMany(DamageReport::class, 'damage_report_disasters');
    }
}
