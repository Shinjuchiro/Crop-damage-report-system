<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\SoftDeletable;
use Illuminate\Database\Eloquent\Model;

class Association extends Model
{
    use Archivable, SoftDeletable;

    protected $table = 'associations';

    protected $fillable = ['name', 'location', 'barangay_id', 'description'];

    protected function casts(): array
    {
        // archived_at cast added Sept 2026 - see Crop::casts() for why.
        return ['archived_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    /** Where the association's office sits. Used to place it on the map. */
    public function barangay()
    {
        return $this->belongsTo(Barangay::class);
    }

    public function farmers()
    {
        return $this->hasMany(Farmer::class);
    }

    public function officers()
    {
        return $this->hasMany(AssociationOfficer::class);
    }

    public function assistanceAllocations()
    {
        return $this->hasMany(AssistanceAllocation::class);
    }

    /**
     * The acronym in brackets if the association has one, otherwise a short
     * form of the name. Used for map labels where space is tight.
     */
    public function getShortNameAttribute(): string
    {
        if (preg_match('/\(([A-Z0-9&]{2,12})\)/', $this->name, $matches)) {
            return $matches[1];
        }

        return \Illuminate\Support\Str::limit($this->name, 22, '');
    }
}
