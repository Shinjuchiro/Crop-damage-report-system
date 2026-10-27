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

    /** "Typhoon", "Extreme Heat", ... the label a person reads for this event's type. */
    public function getTypeLabelAttribute(): string
    {
        $label = DamageReport::CAUSES[$this->type] ?? null;

        return $label ? explode(' / ', $label)[0] : ucwords(str_replace('_', ' ', (string) $this->type));
    }

    /** Every advisory announcement that carries this event. */
    public function broadcasts()
    {
        return $this->hasMany(NotificationBroadcast::class);
    }

    /**
     * The events a farmer may pick on a damage report.
     *
     * Active (not archived or deleted), and: either entered by the office in
     * Disaster Management (no advisory involved), or announced by an advisory
     * that has actually been SENT. An advisory that is still scheduled, or
     * that failed to reach anybody, does not make its event selectable yet.
     * An announcement with no structured disaster record never produces a
     * Disaster row at all, so it can never appear here.
     */
    public function scopeOfferedToFarmers($query)
    {
        // An event with no official date cannot be used to check dates, so it is
        // never offered (Oct 2026).
        return $query->active()->whereNotNull('date_start')->where(function ($w) {
            $w->whereDoesntHave('broadcasts')
              ->orWhereHas('broadcasts', fn ($b) => $b->where('status', 'sent'));
        });
    }

    /** The Disaster Advisory that announced this event, if one did. */
    public function advisory()
    {
        return $this->hasOne(NotificationBroadcast::class)->latestOfMany();
    }

    public function damageReports()
    {
        return $this->belongsToMany(DamageReport::class, 'damage_report_disasters');
    }
}
