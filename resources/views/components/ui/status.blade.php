{{--
    One place that decides what colour a status is.

    <x-ui.status value="{{ $report->status }}" />
    <x-ui.status value="{{ $farmer->verification_status }}" />

    Add new statuses here rather than colouring them inside a page, otherwise
    "Verified" ends up green on one screen and blue on another.

    FOUR COLOURS (Sept 2026)

    Green for a good outcome, amber for something still waiting, red for a
    bad outcome, grey for everything in between and for anything switched
    off. Blue and purple were retired: blue said nothing green, amber and
    grey were not already saying, and one purple badge among them read as a
    fifth meaning that did not exist.

    In-progress states (Assigned, Under Verification, Scheduled, Allocated)
    are the filled grey, and switched-off ones (Inactive, Archived, Draft)
    stay the outlined grey, so the two are still told apart.
--}}
@props(['value'])

@php
    $key = strtolower(str_replace([' ', '-'], '_', (string) $value));

    $map = [
        // Accounts and registrations
        'pending'            => ['warning', 'Pending'],
        'verified'           => ['success', 'Verified'],
        'approved'           => ['success', 'Approved'],
        'rejected'           => ['danger',  'Rejected'],
        'active'             => ['success', 'Active'],
        'inactive'           => ['outline', 'Inactive'],
        'archived'           => ['outline', 'Archived'],

        // Damage reports. Assigned and Under Verification are work in
        // progress, so they take the filled grey.
        'assigned'           => ['neutral', 'Assigned'],
        'under_verification' => ['neutral', 'Under Verification'],
        'flagged'            => ['danger',  'Flagged'],

        // Damage severity. Total is the same red as Partial; the word is
        // what separates them, as it does everywhere else here.
        'slight'             => ['success', 'Slight'],
        'moderate'           => ['warning', 'Moderate'],
        'partial'            => ['danger',  'Partial'],
        'total'              => ['danger',  'Total'],

        // Assistance
        'allocated'          => ['neutral', 'Allocated'],

        // Receipt of assistance, as the farmer answered it. Added here
        // rather than coloured inside the distribution pages, so the same
        // answer looks the same on the MAO list, the card and the panel.
        'pending_confirmation' => ['warning', 'Awaiting Confirmation'],
        'confirmed_received'   => ['success', 'Confirmed Received'],
        'not_received'         => ['danger',  'Not Received'],
        'distributed'        => ['success', 'Distributed'],
        'completed'          => ['success', 'Completed'],

        // Alerts and messages
        'draft'              => ['outline', 'Draft'],
        'scheduled'          => ['neutral', 'Scheduled'],
        'sent'               => ['success', 'Sent'],
        'delivered'          => ['success', 'Delivered'],
        'failed'             => ['danger',  'Failed'],

        // Alert priority
        'normal'             => ['outline', 'Normal'],
        'important'          => ['neutral', 'Important'],
        'urgent'             => ['warning', 'Urgent'],
        'critical'           => ['danger',  'Critical'],
    ];

    [$variant, $label] = $map[$key] ?? ['default', ucwords(str_replace('_', ' ', (string) $value))];
@endphp

<x-ui.badge :variant="$variant" dot {{ $attributes }}>{{ $label }}</x-ui.badge>
