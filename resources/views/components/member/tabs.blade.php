{{--
    The member profile's tab strip.

        <x-member.tabs :farmer="$farmer" :current="$tab" />

    Six tabs across the office's farmer profile and the association's member
    profile, so the same record reads the same way whoever opens it
    (revision section 9).

    LINKS, NOT JAVASCRIPT

    Each tab is ?tab=<key> on the page's own URL, which means a tab can be
    linked to, sent to a colleague, reloaded and reached with the browser's
    Back button. fullUrlWithQuery keeps everything else already in the query
    string, so a tab never loses a filter or a page number it arrived with.

    The counts are what make the strip worth having: "Crop Damage 2" answers
    the question before the tab is opened, and a 0 saves the trip entirely.
    They come from relations the page has already loaded, so they cost no
    queries.

    Expects $farmer (a Farmer) with plantingRecords, damageReports.validation
    and assistanceDistributions loaded, and $current, the active tab key.
--}}

@props([
    'farmer',
    'current' => 'overview',
])

@php
    $inspected = $farmer->damageReports
        ->filter(fn ($report) => $report->validation?->validated_at)
        ->count();

    $tabs = [
        'overview'   => ['label' => 'Overview',   'count' => null],
        'planting'   => ['label' => 'Crop Planted', 'count' => $farmer->plantingRecords->count()],
        'damage'     => ['label' => 'Crop Damage',  'count' => $farmer->damageReports->count()],
        'validation' => ['label' => 'Validation & Assessment', 'count' => $inspected],
        'assistance' => ['label' => 'Assistance',   'count' => $farmer->assistanceDistributions->count()],
        'history'    => ['label' => 'History',      'count' => null],
    ];

    $items = [];

    foreach ($tabs as $key => $tab) {
        $items[] = [
            'label'  => $tab['label'],
            'url'    => request()->fullUrlWithQuery(['tab' => $key]),
            'active' => $current === $key,
        ] + ($tab['count'] === null ? [] : ['count' => $tab['count']]);
    }
@endphp

<x-ui.tabs :items="$items" {{ $attributes }} />
