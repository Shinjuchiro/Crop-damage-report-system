{{--
    One place that decides what colour a status is.

    <x-ui.status value="{{ $report->status }}" />
    <x-ui.status value="{{ $farmer->verification_status }}" />

    Add new statuses here rather than colouring them inside a page, otherwise
    "Verified" ends up green on one screen and blue on another.
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

        // Damage reports
        'assigned'           => ['info',    'Assigned'],
        'under_verification' => ['info',    'Under Verification'],
        'flagged'            => ['danger',  'Flagged'],

        // Damage severity. Total is dark purple, not red, by request.
        'slight'             => ['success', 'Slight'],
        'moderate'           => ['warning', 'Moderate'],
        'partial'            => ['danger',  'Partial'],
        'total'              => ['purple',  'Total'],

        // Assistance
        'allocated'          => ['info',    'Allocated'],
        'distributed'        => ['success', 'Distributed'],
        'completed'          => ['success', 'Completed'],

        // Alerts and messages
        'draft'              => ['outline', 'Draft'],
        'scheduled'          => ['info',    'Scheduled'],
        'sent'               => ['success', 'Sent'],
        'delivered'          => ['success', 'Delivered'],
        'failed'             => ['danger',  'Failed'],

        // Alert priority
        'normal'             => ['outline', 'Normal'],
        'important'          => ['info',    'Important'],
        'urgent'             => ['warning', 'Urgent'],
        'critical'           => ['danger',  'Critical'],
    ];

    [$variant, $label] = $map[$key] ?? ['default', ucwords(str_replace('_', ' ', (string) $value))];
@endphp

<x-ui.badge :variant="$variant" dot {{ $attributes }}>{{ $label }}</x-ui.badge>
