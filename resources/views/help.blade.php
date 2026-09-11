@extends('layouts.app')

@section('title', 'Help')
@section('heading', 'Help')
@section('heading-fil', 'Tulong')
@section('subheading', 'How to use the system, and who to contact when you are stuck.')

@section('content')

{{-- A plain help page. The "Need help?" button in the top bar and the
     "Contact Admin" button on the login screen both land here, so neither
     of them is a dead end. --}}

<div class="space-y-4">

    <x-ui.card title="Contact the Municipal Agriculture Office"
               description="Makipag-ugnayan sa tanggapan ng MAO">
        <p class="text-sm leading-relaxed text-muted-foreground">
            For anything this system cannot fix by itself, corrections to your registered details,
            a report you submitted by mistake, or assistance you believe was recorded wrongly,
            contact the office directly. They can change records that you cannot.
        </p>
        <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
            Para sa anumang katanungan o pagwawasto sa inyong impormasyon, makipag-ugnayan po sa
            tanggapan ng Municipal Agriculture Office ng Tanza.
        </p>
    </x-ui.card>

    @php
        // Short answers to the questions people actually ask. Written plainly,
        // with the Filipino line underneath, because the farmers reading this
        // are the same ones the whole system is built around.
        $faqs = [
            [
                'q'   => 'How do I report crop damage?',
                'fil' => 'Paano mag-ulat ng pinsala?',
                'a'   => 'Tap the green button in the middle of the bottom bar, or open Report Crop '
                       . 'Damage from the menu. Fill in the crop, how much land was damaged, your '
                       . 'estimate of the damage, and add photos. Nothing is saved until you press '
                       . 'Confirm on the review screen.',
            ],
            [
                'q'   => 'Why does my account say Inactive?',
                'fil' => 'Bakit Inactive ang aking account?',
                'a'   => 'Because no farm activity has been recorded for three months. Record a crop '
                       . 'planting activity and your account becomes Active again straight away. '
                       . 'Having no damage to report does not make you inactive.',
            ],
            [
                'q'   => 'The location button does not work.',
                'fil' => 'Ayaw gumana ng location.',
                'a'   => 'Your phone may have refused permission, or there is no signal where you are '
                       . 'standing. You can still submit: describe how to find the farm in words. '
                       . 'The technician will pin the exact spot during the inspection.',
            ],
            [
                'q'   => 'Can I edit a report after sending it?',
                'fil' => 'Puwede ko pa bang baguhin ang naisumite kong ulat?',
                'a'   => 'No. Once submitted, the report is the evidence the technician inspects, so '
                       . 'it has to stop changing. If something is wrong, contact the office.',
            ],
            [
                'q'   => 'How do I install this on my phone?',
                'fil' => 'Paano ito i-install sa telepono?',
                'a'   => 'Look for the Install app button at the top of the screen. On an iPhone, open '
                       . 'the Share menu in Safari and choose Add to Home Screen.',
            ],
        ];
    @endphp

    <x-ui.card title="Common questions" description="Mga karaniwang tanong" :padded="false">
        <div class="divide-y divide-border">
            @foreach ($faqs as $faq)
                {{-- Each question opens on its own. Alpine keeps this simple:
                     one boolean per item, no JavaScript file needed. --}}
                <div x-data="{ open: false }" class="px-5 py-1">
                    <button type="button" @click="open = ! open"
                            class="flex w-full items-center justify-between gap-4 py-3 text-left">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold">{{ $faq['q'] }}</span>
                            <span class="block text-xs text-muted-foreground">{{ $faq['fil'] }}</span>
                        </span>

                        <svg class="h-5 w-5 shrink-0 text-muted-foreground transition-transform"
                             :class="open && 'rotate-180'"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>

                    <p x-show="open" x-cloak
                       x-transition:enter="transition ease-out duration-150"
                       x-transition:enter-start="opacity-0 -translate-y-1"
                       class="pb-4 text-sm leading-relaxed text-muted-foreground">
                        {{ $faq['a'] }}
                    </p>
                </div>
            @endforeach
        </div>
    </x-ui.card>
</div>
@endsection
