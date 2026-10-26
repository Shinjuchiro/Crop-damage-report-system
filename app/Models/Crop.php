<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\SoftDeletable;
use Illuminate\Database\Eloquent\Model;

class Crop extends Model
{
    use Archivable, SoftDeletable;

    protected $table = 'crops';

    protected $fillable = ['name', 'is_hvcc', 'growing_days'];

    protected function casts(): array
    {
        // archived_at was missing here before Sept 2026 - a plain DB string
        // rather than Carbon, which threw the moment anything called
        // ->format() on it (every archived crop shown on the Archive page).
        return ['is_hvcc' => 'boolean', 'growing_days' => 'integer', 'archived_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    /**
     * The order crops are offered in: Rice, Corn, HVCC, then anything the
     * office has added since, alphabetically.
     *
     * The revision asks for that order specifically, and it is not
     * alphabetical: by name the list reads Corn, HVCC, Rice, which puts the
     * municipality's main crop last. It is not insertion order either,
     * because the office can add, archive and re-add a crop through Crop
     * Management, so an id records when that happened rather than anything
     * a farmer filling in a form cares about.
     *
     * So the three known crops are placed by name and everything else
     * follows them. A crop the office adds later needs no code change: it
     * lands in the ELSE group and sorts alphabetically within it.
     *
     * A scope on the model rather than an ORDER BY written into each
     * controller, because eight screens offer this list, and an order that
     * differs between the farmer's form and the office's filter is the kind
     * of thing nobody notices until a panel asks about it.
     */
    /**
     * The ESTIMATED harvest date for a planting of this crop: the date
     * planted plus the crop's usual growing days (set in Crop Management).
     * Null when the office has not set a growing period for the crop, so
     * the page can say "not available" instead of inventing a date.
     */
    public function estimatedHarvest($datePlanted): ?\Carbon\CarbonInterface
    {
        if (! $this->growing_days || ! $datePlanted) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($datePlanted)->addDays((int) $this->growing_days);
    }

    public function scopeInDisplayOrder($query)
    {
        return $query
            ->orderByRaw("CASE crops.name
                              WHEN 'Corn' THEN 1
                              WHEN 'Rice' THEN 2
                              WHEN 'HVCC' THEN 3
                              ELSE 4
                          END")
            ->orderBy('crops.name');
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
