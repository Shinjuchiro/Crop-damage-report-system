<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A photo the TECHNICIAN took during the field inspection.
 *
 * Kept in its own table, away from damage_report_photos, because those belong
 * to the farmer (proposal section 36). Two people, two sets of evidence,
 * never mixed together.
 */
class ValidationPhoto extends Model
{
    /**
     * The two shots the inspection form asks for by name, plus a catch-all.
     *
     * Storing the type means that months later somebody opening the record
     * can still tell which photo was the wide shot of the whole field and
     * which was the close-up of the plants themselves.
     */
    public const SHOT_TYPES = [
        'wide'    => 'Wide Shot',
        'closeup' => 'Close-up Shot',
        'other'   => 'Additional Photo',
    ];

    protected $table = 'validation_photos';
    public $timestamps = false;

    protected $fillable = ['validation_id', 'shot_type', 'file_path', 'uploaded_at'];

    public function validation()
    {
        return $this->belongsTo(Validation::class);
    }

    public function getShotLabelAttribute(): string
    {
        return self::SHOT_TYPES[$this->shot_type] ?? self::SHOT_TYPES['other'];
    }
}
