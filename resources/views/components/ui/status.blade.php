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

        /* The membership application's four open stages (revision step 6).
           See App\Support\MembershipApplication, which works out which one
           an application is in; the keys here are its constants. Its two
           outcomes are not listed again: they resolve to 'approved' and
           'rejected', already above, which is the point of resolving them
           from the account's own status rather than storing them twice.

           Submitted is amber: waiting on somebody, and nobody has opened it.
           Under Association Review and Forwarded to MAO are the filled grey
           of work in progress, the same as Assigned and Under Verification
           below, because that is exactly what they are.

           Returned for Correction is red, and it is the one choice here
           worth defending. Amber would read as "waiting", which is the
           opposite of what this stage asks: it is the only stage where
           somebody, specifically the farmer, has to go and do something, and
           an amber badge invites them to keep waiting for a decision that is
           not coming. 'flagged' above is red for the same reason. */
        'submitted_to_association' => ['warning', 'Submitted to Association'],
        'under_association_review' => ['neutral', 'Under Association Review'],
        'forwarded_to_mao'         => ['neutral', 'Forwarded to MAO'],
        'returned_for_correction'  => ['danger',  'Returned for Correction'],
        // The office sent it back to the association. Amber: the association
        // has to act, and nobody else is being asked to.
        'returned_to_association'  => ['warning', 'Returned to Association'],

        // Damage reports. Assigned and Under Verification are work in
        // progress, so they take the filled grey.
        'assigned'           => ['neutral', 'Assigned'],
        'under_verification' => ['neutral', 'Under Verification'],
        'flagged'            => ['danger',  'Flagged'],

        // Damage severity. Three levels since Oct 2026; 'partial' stays in
        // this list on purpose, and only here among the places that offer a
        // severity, so an inspection recorded under the old four-level
        // scale still reads "Partial" on its own pages instead of falling
        // back to a bare word or an empty badge. Nothing can record it any
        // more: the inspection form and the controller both work from
        // Validation::SEVERITY_SCALE, which has the three.
        'slight'             => ['success', 'Slight'],
        'moderate'           => ['warning', 'Moderate'],
        'total'              => ['danger',  'Total'],
        'partial'            => ['danger',  'Partial'],

        // Assistance
        'allocated'          => ['neutral', 'Allocated'],

        /* The four main assistance statuses (revision section 11.1). These
           describe one farmer's case: see App\Support\AssistanceCase, which
           works out which one a case is in. The keys here are its constants.

           The colours follow this file's own convention rather than
           inventing a fifth meaning. Pending Review is amber because it is
           waiting on somebody. Designated is the filled grey of an
           in-progress state: the office has said yes and nothing has been
           handed over yet. Not Designated is the outlined grey of a state
           that is switched off, not the red of a failure, because refusing
           one allocation is a normal administrative outcome and not a
           black mark against the farmer. Received is green: it is the only
           one of the four that means the farmer has the assistance.

           The labels are the revision's own, in full. "Designated" alone
           invites being read as "given", which is the most expensive
           misreading this module can produce. */
        'pending_review'     => ['warning', 'Pending Review'],
        'designated'         => ['neutral', 'Designated for Assistance'],
        'not_designated'     => ['outline', 'Not Designated for Assistance'],
        'received'           => ['success', 'Received'],

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
