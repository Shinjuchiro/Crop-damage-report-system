<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * "By farmer" lists, paginated by FARMER (Oct 2026).
 *
 * Crop Planted, Crop Damage and Validation Monitoring show one collapsed row
 * per farmer. Opening a row shows ALL of that farmer's records, so the list
 * no longer keeps only their latest three, and the page stays short because
 * a page holds a handful of farmers rather than fifteen records.
 *
 * It used to paginate rows, then print a farmer's name above each run of
 * rows. That is why a farmer's block could be cut in half by a page break,
 * and why the "latest 3" cap was needed at all.
 *
 * HOW IT WORKS
 *
 * 1. One query pages the FARMERS that have at least one matching record,
 *    ordered by who filed most recently. Every filter on the page has
 *    already been applied to $records, so a search or filter can never show
 *    a farmer with nothing matching.
 * 2. A second query loads every matching record for exactly those farmers.
 * 3. The two are joined back together in the page's farmer order.
 *
 * The result is an ordinary paginator whose items are the records, grouped
 * so that each farmer's records are next to each other. Its TOTAL and its
 * per page figure count farmers, not records: the footer says so.
 */
class FarmerGroups
{
    public const PER_PAGE = 5;

    /**
     * @param  Builder  $records   the page's filtered query, unordered and not paginated
     * @param  Closure  $groupIds  fn (\Illuminate\Database\Query\Builder $q) => $q  selects the
     *                             farmer id AS farmer_id, groups by it and orders the groups
     * @param  Closure  $rows      fn (Builder $q, array $ids) => $q  restricts to those farmers
     *                             and orders each one's records
     * @param  Closure  $farmerOf  fn ($record) => ?int  the farmer a record belongs to
     */
    public static function paginate(
        Builder $records,
        Closure $groupIds,
        Closure $rows,
        Closure $farmerOf,
        int $perPage = self::PER_PAGE
    ): LengthAwarePaginator {
        /* The farmer ids come from the same filtered query, stripped back to
           a plain query: no columns, no ordering, and no select bindings,
           because the page's own query may carry a withSum() sub-select that
           has no business in a GROUP BY. */
        $base = $records->clone()->toBase();
        $base->columns = null;
        $base->orders = null;
        $base->bindings['select'] = [];
        $base->bindings['order'] = [];

        $page = $groupIds($base)->paginate($perPage);

        $ids = collect($page->items())->pluck('farmer_id')->map(fn ($id) => (int) $id)->all();

        $loaded = $ids === []
            ? collect()
            : $rows($records->clone(), $ids)->get()->groupBy(fn ($record) => (int) $farmerOf($record));

        // Back into the page's order of farmers; each farmer's records keep
        // the order $rows() gave them.
        $ordered = collect($ids)->flatMap(fn ($id) => $loaded->get($id, collect()))->values();

        return (new LengthAwarePaginator(
            $ordered,
            $page->total(),
            $perPage,
            $page->currentPage(),
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']
        ))->withQueryString();
    }

    /** "Showing 1 to 5 of 12 farmers", worked out from farmers, not rows. */
    public static function range(LengthAwarePaginator $page): array
    {
        $total = $page->total();
        $first = $total > 0 ? ($page->currentPage() - 1) * $page->perPage() + 1 : 0;
        $last  = $total > 0 ? min($total, $page->currentPage() * $page->perPage()) : 0;

        return [$first, $last, $total];
    }
}
