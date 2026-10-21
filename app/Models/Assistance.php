<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletable;
use Illuminate\Database\Eloquent\Model;

class Assistance extends Model
{
    use SoftDeletable;

    protected $table = 'assistances'; // explicit - "assistance" does not pluralize predictably

    /* NO TYPE, NO AMOUNT (Oct 2026, client decision)

       An assistance item is now a name, a description and what it is for.
       It is not classified as cash or in-kind, and it carries no figure.

       The reason is privacy, and it is the client's own: how much a named
       farmer received is a financial detail about an identifiable person,
       and the people who work these screens - association officers, office
       staff, anyone who can open a monthly report - have no need to see it.
       The office tracks who was assisted; the amounts live in the office's
       own financial records, outside this system.

       This deliberately departs from the master prompt's sections 63, 65
       and 75, which name Type and Amount/Quantity. The client asked for the
       change after those sections were written, and the running system
       follows the client.

       The columns `type` and `available_quantity_or_amount` are still on
       the table and are not in this list, so nothing can write to them and
       no screen reads them. They were left in place rather than dropped so
       that figures recorded before this change are not destroyed by a
       migration; see the Oct 2026 entry in BUILD-STATUS. */
    protected $fillable = [
        'name', 'description', 'disaster_id', 'crop_id', 'status',
    ];

    protected function casts(): array
    {
        return ['deleted_at' => 'datetime'];
    }

    public function disaster()
    {
        return $this->belongsTo(Disaster::class);
    }

    public function crop()
    {
        return $this->belongsTo(Crop::class);
    }

    public function allocations()
    {
        return $this->hasMany(AssistanceAllocation::class);
    }

    /* getRemainingQuantityAttribute() was removed with the amounts. It
       computed an available pool minus everything allocated out of it,
       which has nothing left to work from and nothing left to display.
       Anything that needs to know how much assistance is left asks the
       office, not this system. */
}
