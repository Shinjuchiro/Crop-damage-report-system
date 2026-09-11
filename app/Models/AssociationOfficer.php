<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssociationOfficer extends Model
{
    protected $table = 'association_officers';

    protected $fillable = ['user_id', 'association_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function association()
    {
        return $this->belongsTo(Association::class);
    }
}
