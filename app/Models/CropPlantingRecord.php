<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\SoftDeletable;
use Illuminate\Database\Eloquent\Model;

/**
 * Sept 2026: joined the Archive page as its own tab (migration
 * 2024_01_16_000001) so MAO can retire a mistaken or duplicate submission -
 * see Archivable/SoftDeletable for what archiving does and does not affect.
 *
 * Archiving or editing a record here never rewrites a farmer's already
 * computed Active/Inactive history (section 21-22): Farmer::sweepInactive()
 * and recordQualifyingActivity() only ever look at the current state of
 * qualifying activity going forward, they do not replay history, so nothing
 * extra is needed here to keep that true.
 */
class CropPlantingRecord extends Model
{
    use Archivable, SoftDeletable;

    protected $table = 'crop_planting_records';

    protected $fillable = ['farmer_id', 'date_submitted'];

    protected function casts(): array
    {
        return ['date_submitted' => 'date', 'archived_at' => 'datetime', 'deleted_at' => 'datetime'];
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
