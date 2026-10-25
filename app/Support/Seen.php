<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "New to me" counts for the Crop Management Report badges.
 *
 * A record counts for a person until THEY open it (its View). It is per
 * person: one officer opening a report does not clear it for another.
 *
 * Three kinds, one per tab:
 *   planting       a planting record filed by a farmer
 *   damage_report  a damage report filed by a farmer
 *   validation     an inspection a technician has finished (counted again
 *                  when it finishes, even if the report itself was seen)
 *
 * Only records newer than the person's badges_since count, so existing
 * records do not all appear as new on day one. An association officer only
 * ever counts their own members' records.
 */
class Seen
{
    public static function mark(?User $user, string $type, ?int $id): void
    {
        if (! $user || ! $id) {
            return;
        }

        DB::table('record_views')->insertOrIgnore([
            'user_id'     => $user->id,
            'record_type' => $type,
            'record_id'   => $id,
            'seen_at'     => now(),
        ]);
    }

    /** @return array{planting:int, damage:int, validation:int} */
    public static function counts(User $user, ?int $associationId = null): array
    {
        $since = $user->badges_since ?? $user->created_at ?? now();

        $unseen = fn (string $type, string $idColumn) => fn ($q) => $q->whereNotExists(
            fn ($s) => $s->select(DB::raw(1))->from('record_views as rv')
                ->where('rv.user_id', $user->id)
                ->where('rv.record_type', $type)
                ->whereColumn('rv.record_id', $idColumn)
        );

        $ofAssociation = fn ($q, string $farmerColumn) => $associationId
            ? $q->whereExists(fn ($s) => $s->select(DB::raw(1))->from('farmers as f')
                ->whereColumn('f.id', $farmerColumn)->where('f.association_id', $associationId))
            : $q;

        $planting = DB::table('crop_planting_records as r')
            ->whereNull('r.deleted_at')->whereNull('r.archived_at')
            ->where('r.date_submitted', '>', $since)
            ->tap($unseen('planting', 'r.id'))
            ->tap(fn ($q) => $ofAssociation($q, 'r.farmer_id'))
            ->count();

        $damage = DB::table('damage_reports as d')
            ->whereNull('d.deleted_at')->whereNull('d.archived_at')
            ->where('d.created_at', '>', $since)
            ->tap($unseen('damage_report', 'd.id'))
            ->tap(fn ($q) => $ofAssociation($q, 'd.farmer_id'))
            ->count();

        $validation = DB::table('validations as v')
            ->join('damage_reports as d', 'd.id', '=', 'v.damage_report_id')
            ->whereNull('d.deleted_at')->whereNull('d.archived_at')
            ->whereNotNull('v.validated_at')
            ->where('v.validated_at', '>', $since)
            ->tap($unseen('validation', 'd.id'))
            ->tap(fn ($q) => $ofAssociation($q, 'd.farmer_id'))
            ->count();

        return ['planting' => $planting, 'damage' => $damage, 'validation' => $validation];
    }
}
