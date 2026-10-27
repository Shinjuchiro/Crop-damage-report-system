{{--
    ONE FARMER, FOLDED, WITH THEIR RECORDS INSIDE (Oct 2026)

        <x-record.farmer-block :farmer="$farmer" :count="3" noun="report"
                               :group-id="$farmer->id"
                               :href="route('mao.farmers.show', $farmer)"
                               :headers="[['Report ID'], ['Farmer'], ['Status', 'text-center'], ['Action', 'text-center']]">
            <tr>...one row per record, four cells to match the headers...</tr>
        </x-record.farmer-block>

    Used on the three Crop Management Report tabs (Crop Damage, Validation,
    Crop Planted) when the list is grouped by farmer. It draws TWO rows inside
    the host table:

      1. The farmer row: FARMER ID | FARMER | ASSOCIATION | NUMBER OF REPORTS (or RECORDS).
         No profile link; the profile is reached from the Farmers Directory.
         Clicking it opens and closes the block.
      2. A hidden row holding the farmer's own table, with its own heading
         band. The records, and their column headings, are only on the page
         while the farmer is open, so a page of five farmers stays short.

    The host table must have five columns (Farmer ID, Farmer, Association, Number of ..., and a narrow last one for the arrow)
    and sit inside an Alpine scope that holds `open`, the id of the farmer
    whose block is open. One block is open at a time.

    props
      farmer   the Farmer whose block this is (may be null for orphaned rows)
      count    how many of their records are in the block
      noun     singular noun for the count, pluralised here
      groupId  the farmer's id, the value `open` is compared with
      href     unused; kept so existing callers do not break
      headers  heading cells of the inner table: [label, extra classes]
      seen     'damage_report' | 'planting' | 'validation': shows a red "N new"
               beside the count for records this user has not opened yet
      association  false drops the Association column (the association's own
               screens: every member is in the same one, so it says nothing)
--}}

@props([
    'farmer',
    'count'   => 0,
    'noun'    => 'record',
    'groupId' => 0,
    'href'    => null,
    'headers' => [],
    'association' => true,
    'seen'    => null,
])

@php
    $gid   = (int) $groupId;
    $code  = $farmer ? 'FARM-' . str_pad((string) $farmer->id, 3, '0', STR_PAD_LEFT) : '-';
    $where = $farmer?->barangay?->name ?? '';

    // How many of this farmer's records the signed in user has not opened yet.
    $newCount = ($seen && $farmer)
        ? (\App\Support\Seen::newByFarmer(auth()->user(), $seen)[$farmer->id] ?? 0)
        : 0;
@endphp

{{-- 1. THE FARMER ROW --}}
<tr class="cursor-pointer select-none hover:bg-muted/60"
    :class="open === {{ $gid }} ? 'bg-muted/60' : ''"
    @click="open = open === {{ $gid }} ? null : {{ $gid }}"
    @keydown.enter.prevent="open = open === {{ $gid }} ? null : {{ $gid }}"
    tabindex="0" role="button"
    :aria-expanded="(open === {{ $gid }}).toString()">
    <td class="px-4 py-4 text-foreground">{{ $code }}</td>

    <td class="px-4 py-4">
        <div class="flex min-w-0 items-center gap-2">
            <div class="min-w-0">
                <p class="font-bold text-foreground">{{ $farmer?->full_name ?? 'Unknown member' }}</p>
                @if ($where !== '')
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ $where }}</p>
                @endif
            </div>
        </div>
    </td>

    @if ($association)
        <td class="px-4 py-4 text-muted-foreground">{{ $farmer?->association?->name ?? '-' }}</td>
    @endif

    <td class="px-4 py-4 text-center">
        <span class="inline-flex items-center justify-center gap-2">
            <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-primary">
                {{ number_format($count) }}
            </span>
            @if ($newCount > 0)
                {{-- New to this user: filed since they last looked and not yet
                     opened with View. Same rule as the tab badges above. --}}
                <span class="rounded-full px-2 py-0.5 text-[11px] font-bold leading-none"
                      style="background:#b91c1c;color:#fff;"
                      title="{{ $newCount }} new, not yet opened">
                    {{ $newCount }} new
                </span>
            @endif
        </span>
    </td>

    {{-- The open/close arrow sits at the far right of the row, where the
         eye expects a row's action to be, not squeezed in beside the name. --}}
    <td class="w-12 px-4 py-4 text-right">
        <svg class="inline h-4 w-4 text-muted-foreground transition-transform"
             :class="open === {{ $gid }} ? 'rotate-90' : ''"
             fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
             stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M9 6l6 6-6 6"/>
        </svg>
    </td>
</tr>

{{-- 2. THEIR RECORDS, ONLY WHILE OPEN --}}
<tr x-show="open === {{ $gid }}" x-cloak>
    <td colspan="{{ $association ? 5 : 4 }}" class="p-0">
        <div class="overflow-x-auto border-t border-border bg-card">
            <table class="w-full text-left">
                <thead class="bg-muted/70">
                    <tr class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        @foreach ($headers as $header)
                            <th class="px-4 py-2.5 {{ $header[1] ?? '' }}">{{ $header[0] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-sm">
                    {{ $slot }}
                </tbody>
            </table>
        </div>
    </td>
</tr>
