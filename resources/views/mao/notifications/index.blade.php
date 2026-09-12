@extends('layouts.app')

@section('title', 'Notification and Alerts')
@section('heading', 'Notification and Alerts')
@section('subheading', 'Advisories, deadlines and announcements sent to farmers, associations and technicians.')

@php
    $broadcast = \App\Models\NotificationBroadcast::class;

    $categoryStyles = [
        'disaster_alert'     => ['bg-red-100', 'text-red-700', 'bg-red-50 text-red-700 dark:bg-red-950/60 dark:text-red-200 ring-red-200'],
        'agricultural_alert' => ['bg-green-100', 'text-green-700', 'bg-accent text-green-800 ring-green-200'],
        'event'              => ['bg-sky-100', 'text-sky-700', 'bg-sky-50 text-sky-800 dark:bg-sky-950/60 dark:text-sky-200 ring-sky-200'],
        'system'             => ['bg-violet-100', 'text-violet-700', 'bg-violet-50 text-violet-800 ring-violet-200'],
        'assistance'         => ['bg-amber-100', 'text-amber-700', 'bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-200 ring-amber-200'],
        'announcement'       => ['bg-fuchsia-100', 'text-fuchsia-700', 'bg-fuchsia-50 text-fuchsia-800 ring-fuchsia-200'],
    ];

    $categoryIcons = [
        'disaster_alert'     => 'M12 9v4M12 17h.01M10.3 3.9L2.4 17.5A2 2 0 004.1 20.5h15.8a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
        'agricultural_alert' => 'M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14z',
        'event'              => 'M8 3v3M16 3v3M4 9h16M5 6h14a1 1 0 011 1v12a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1z',
        'system'             => 'M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4',
        'assistance'         => 'M20 8H4a1 1 0 00-1 1v3h18V9a1 1 0 00-1-1zM4 12v8a1 1 0 001 1h14a1 1 0 001-1v-8M12 8V21M12 8S9.5 3 7.5 4.5 9.5 8 12 8zM12 8s2.5-5 4.5-3.5S14.5 8 12 8z',
        'announcement'       => 'M3 11v2a1 1 0 001 1h3l5 4V6L7 10H4a1 1 0 00-1 1zM16 8.5a5 5 0 010 7M19 6a9 9 0 010 12',
    ];

    $statusStyles = [
        'sent'      => ['Sent', 'bg-accent0'],
        'scheduled' => ['Scheduled', 'bg-sky-500'],
        'draft'     => ['Draft', 'bg-slate-400'],
        'failed'    => ['Failed', 'bg-red-500'],
        'archived'  => ['Archived', 'bg-slate-300'],
    ];
@endphp

@section('header-actions')
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button variant="outline" :href="route('mao.sms-history.index')">SMS History</x-ui.button>
        <button type="button" @click="$dispatch('open-alert-composer')"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110">
            <span class="text-base leading-none">+</span> Create New Alert
        </button>
    </div>
@endsection

@section('content')
<div x-data="alertComposer()" @open-alert-composer.window="open()">

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Tabs --}}
    <div class="mb-5 overflow-x-auto">
        <div class="flex min-w-max gap-1 rounded-xl border border-border bg-card p-1.5 shadow-sm">
            <a href="{{ route('mao.notifications.index') }}"
               class="rounded-lg px-5 py-2.5 text-sm font-medium transition {{ ! $category ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-muted/60' }}">
                All
                <span class="ml-1 text-xs opacity-70">{{ $totalAlerts }}</span>
            </a>

            @foreach ($broadcast::CATEGORIES as $key => $label)
                <a href="{{ route('mao.notifications.index', ['category' => $key]) }}"
                   class="rounded-lg px-5 py-2.5 text-sm font-medium transition {{ $category === $key ? 'bg-primary text-white' : 'text-muted-foreground hover:bg-muted/60' }}">
                    {{ $label }}
                    <span class="ml-1 text-xs opacity-70">{{ $perCategory[$key] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Alerts --}}
    {{-- No overflow-hidden here. The row actions menu below is absolutely
         positioned inside this card, and overflow-hidden on an ancestor
         clips an absolute child no matter what its z-index is, so the
         menu on the last row was being cut off at the card edge. --}}
    <div class="rounded-xl border border-border bg-card shadow-sm">
        @if ($alerts->isNotEmpty())
            <ul class="divide-y divide-border">
                @foreach ($alerts as $alert)
                    @php
                        [$iconBg, $iconText, $badge] = $categoryStyles[$alert->category] ?? $categoryStyles['announcement'];
                        [$statusLabel, $statusDot]   = $statusStyles[$alert->status] ?? $statusStyles['draft'];
                    @endphp
                    <li class="flex flex-col gap-4 px-6 py-5 transition hover:bg-muted/60 sm:flex-row sm:items-start">

                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl {{ $iconBg }}">
                            <svg class="h-7 w-7 {{ $iconText }}" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="{{ $categoryIcons[$alert->category] ?? $categoryIcons['announcement'] }}"/>
                            </svg>
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-bold text-foreground">{{ $alert->title }}</h3>
                                <span class="inline-flex rounded px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset {{ $badge }}">
                                    {{ $alert->category_label }}
                                </span>
                                @if ($alert->sends_sms)
                                    <span class="inline-flex rounded bg-secondary px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                        {{ $alert->priority_label }} &middot; SMS
                                    </span>
                                @endif
                            </div>

                            <p class="mt-1 text-sm text-muted-foreground">{{ $alert->message }}</p>

                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ ($alert->sent_at ?? $alert->scheduled_for ?? $alert->created_at)?->format('M d, Y') }}
                                &nbsp;|&nbsp;
                                {{ ($alert->sent_at ?? $alert->scheduled_for ?? $alert->created_at)?->format('g:i A') }}
                                &nbsp;|&nbsp;
                                {{ $alert->audience_label }}
                                @if ($alert->status === 'sent')
                                    &nbsp;|&nbsp; {{ $alert->notifications_count }} recipients,
                                    {{ $alert->read_count }} read
                                @endif
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <span class="flex items-center gap-2 text-sm font-medium text-foreground">
                                <span class="h-2.5 w-2.5 rounded-full {{ $statusDot }}"></span>
                                {{ $statusLabel }}
                            </span>

                            <div class="relative" x-data="{ menu: false }">
                                <button type="button" @click="menu = ! menu" @click.outside="menu = false"
                                        class="rounded p-1.5 text-muted-foreground hover:bg-secondary hover:text-muted-foreground"
                                        aria-label="Alert actions">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                        <circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/>
                                    </svg>
                                </button>

                                <div x-show="menu" x-cloak x-transition
                                     class="absolute right-0 z-50 mt-1 w-44 overflow-hidden rounded-lg border border-border bg-card shadow-lg">
                                    @if (in_array($alert->status, ['scheduled', 'draft', 'failed'], true))
                                        <form method="POST" action="{{ route('mao.notifications.send', $alert) }}">
                                            @csrf @method('PUT')
                                            <button class="block w-full px-4 py-2.5 text-left text-sm text-foreground hover:bg-muted/60">
                                                Send now
                                            </button>
                                        </form>
                                    @endif

                                    @if ($alert->status !== 'archived')
                                        <form method="POST" action="{{ route('mao.notifications.archive', $alert) }}">
                                            @csrf @method('PUT')
                                            <button class="block w-full border-t border-border px-4 py-2.5 text-left text-sm text-foreground hover:bg-muted/60">
                                                Archive
                                            </button>
                                        </form>
                                    @else
                                        <p class="px-4 py-2.5 text-sm text-muted-foreground">Archived</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="px-6 py-16 text-center">
                <p class="text-sm font-medium text-muted-foreground">No alerts here yet</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Use Create New Alert to send an advisory, a deadline reminder or an announcement.
                </p>
            </div>
        @endif
    </div>

    <div class="mt-5">{{ $alerts->links() }}</div>

    {{-- ============================================================
         COMPOSER
    ============================================================= --}}
    <div x-show="showing" x-cloak x-transition.opacity
         class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4 py-10">
        <div x-show="showing" x-transition
             class="w-full max-w-2xl rounded-xl bg-card shadow-xl">

            <form method="POST" action="{{ route('mao.notifications.store') }}"
                  @submit.prevent="step = 'review'">
                @csrf

                <div class="flex items-center justify-between border-b border-border px-6 py-4">
                    <h3 class="text-lg font-semibold text-foreground"
                        x-text="step === 'compose' ? 'Create New Alert' : 'Review Alert'"></h3>
                    <button type="button" @click="showing = false"
                            class="rounded p-1.5 text-muted-foreground hover:bg-secondary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
                        </svg>
                    </button>
                </div>

                {{-- ---------- Compose ---------- --}}
                <div x-show="step === 'compose'" class="space-y-5 px-6 py-5">

                    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">
                                Alert Type <span class="text-red-500">*</span>
                            </label>
                            <select name="category" x-model="f.category" required
                                    class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                                @foreach ($broadcast::CATEGORIES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">
                                Priority <span class="text-red-500">*</span>
                            </label>
                            <select name="priority" x-model="f.priority" required
                                    class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                                @foreach ($broadcast::PRIORITIES as $key => $meta)
                                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-xs" :class="sendsSms() ? 'text-amber-700' : 'text-muted-foreground'"
                               x-text="sendsSms()
                                    ? 'Urgent and Critical alerts are also sent via SMS to each recipient\'s phone number.'
                                    : 'Delivered in-app only.'"></p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-foreground">
                            Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="title" x-model="f.title" required maxlength="255"
                               placeholder="Enter alert title"
                               class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-foreground">
                            Message <span class="text-red-500">*</span>
                        </label>
                        <textarea name="message" x-model="f.message" required rows="4" maxlength="2000"
                                  placeholder="Enter message..."
                                  class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring"></textarea>
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            <span x-text="f.message.length"></span> of 2000 characters.
                        </p>
                    </div>

                    {{-- Recipients --}}
                    <div>
                        <label class="mb-2 block text-sm font-medium text-foreground">
                            Recipients <span class="text-red-500">*</span>
                        </label>

                        <div class="space-y-2 rounded-xl border border-border p-3">
                            @php
                                $audienceMeta = [
                                    'all_farmers'          => ['All Farmers', 'Every verified farmer account'],
                                    'affected_farmers'     => ['Affected Farmers', 'Farmers with at least one damage report'],
                                    'all_associations'     => ['All Associations', 'Every association officer'],
                                    'specific_association' => ['Specific Association', 'Its members and officers'],
                                    'specific_farmer'      => ['Specific Farmer', 'One farmer only'],
                                    'all_technicians'      => ['All Technicians', 'Every active technician'],
                                    'specific_technician'  => ['Specific Technician', 'One technician only'],
                                ];
                            @endphp

                            @foreach ($audienceMeta as $key => [$label, $hint])
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 transition"
                                       :class="f.target_type === '{{ $key }}' ? 'bg-accent ring-1 ring-[#166534]' : 'hover:bg-muted/60'">
                                    <input type="radio" name="target_type" value="{{ $key }}" x-model="f.target_type"
                                           class="h-4 w-4 border-input text-primary focus:ring-ring">
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium text-foreground">{{ $label }}</span>
                                        <span class="block text-xs text-muted-foreground">{{ $hint }}</span>
                                    </span>
                                    @isset($audienceSize[$key])
                                        <span class="shrink-0 text-sm font-semibold tabular-nums text-muted-foreground">
                                            {{ number_format($audienceSize[$key]) }}
                                        </span>
                                    @endisset
                                </label>
                            @endforeach
                        </div>

                        {{-- The one dropdown that matters for the chosen audience --}}
                        <div x-show="f.target_type === 'specific_association'" x-cloak class="mt-3">
                            <select name="target_id" x-model="f.target_id"
                                    class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                                <option value="">Select association</option>
                                @foreach ($associations as $association)
                                    <option value="{{ $association->id }}">{{ $association->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="f.target_type === 'specific_farmer'" x-cloak class="mt-3">
                            <select name="target_id" x-model="f.target_id"
                                    class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                                <option value="">Select farmer</option>
                                @foreach ($farmers as $farmer)
                                    <option value="{{ $farmer->id }}">{{ $farmer->full_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div x-show="f.target_type === 'specific_technician'" x-cloak class="mt-3">
                            <select name="target_id" x-model="f.target_id"
                                    class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring">
                                <option value="">Select technician</option>
                                @foreach ($technicians as $technician)
                                    <option value="{{ $technician->id }}">
                                        {{ $technician->full_name ?: $technician->username }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-foreground">
                            Schedule <span class="font-normal text-muted-foreground">(optional)</span>
                        </label>
                        <input type="datetime-local" name="scheduled_for" x-model="f.scheduled_for"
                               class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring sm:max-w-xs">
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            Leave blank to send immediately.
                        </p>
                    </div>
                </div>

                {{-- ---------- Review ---------- --}}
                <div x-show="step === 'review'" x-cloak class="px-6 py-5">
                    <p class="mb-4 text-sm text-muted-foreground">
                        Check the alert before it goes out. Once sent it cannot be unsent.
                    </p>

                    <dl class="space-y-3 rounded-lg bg-muted p-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Type</dt>
                            <dd class="text-right font-medium text-foreground" x-text="label('categories', f.category)"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Priority</dt>
                            <dd class="text-right font-medium text-foreground" x-text="label('priorities', f.priority)"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Title</dt>
                            <dd class="text-right font-medium text-foreground" x-text="f.title || '-'"></dd>
                        </div>
                        <div>
                            <dt class="mb-1 text-muted-foreground">Message</dt>
                            <dd class="whitespace-pre-line rounded bg-card p-3 text-foreground" x-text="f.message || '-'"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Recipients</dt>
                            <dd class="text-right font-medium text-foreground" x-text="audienceLabel()"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Delivery</dt>
                            <dd class="text-right font-medium text-foreground"
                                x-text="sendsSms() ? 'In-app and SMS' : 'In-app only'"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">When</dt>
                            <dd class="text-right font-medium text-foreground"
                                x-text="f.scheduled_for ? f.scheduled_for.replace('T', ' at ') : 'Immediately'"></dd>
                        </div>
                    </dl>

                    <div x-show="sendsSms()" x-cloak
                         class="mt-4 rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/60 px-4 py-3 text-xs text-amber-800">
                        This priority also sends an SMS to every recipient who has a phone number on file,
                        in addition to the in-app alert. Delivery results appear in SMS History right after sending.
                    </div>
                </div>

                {{-- ---------- Actions ---------- --}}
                <div class="flex flex-col gap-3 border-t border-border px-6 py-4 sm:flex-row">
                    <button type="button" x-show="step === 'compose'" @click="showing = false"
                            class="rounded-lg border border-input px-6 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
                        Cancel
                    </button>

                    <button type="button" x-show="step === 'review'" x-cloak @click="step = 'compose'"
                            class="rounded-lg border border-input px-6 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">
                        Back to Edit
                    </button>

                    <button type="submit" x-show="step === 'compose'"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:brightness-110">
                        Review Alert
                    </button>

                    <button type="button" x-show="step === 'review'" x-cloak @click="$root.submit()"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-white hover:brightness-110">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M2.5 21L23 12 2.5 3v7l14 2-14 2v7z"/>
                        </svg>
                        <span x-text="f.scheduled_for ? 'Schedule Alert' : 'Send Alert'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function alertComposer() {
        return {
            showing: false,
            step: 'compose',

            lookup: {
                categories: @json(\App\Models\NotificationBroadcast::CATEGORIES),
                priorities: @json(collect(\App\Models\NotificationBroadcast::PRIORITIES)->map(fn ($p) => $p['label'])),
                audiences:  @json(\App\Models\NotificationBroadcast::AUDIENCES),
                sms:        @json(collect(\App\Models\NotificationBroadcast::PRIORITIES)->map(fn ($p) => $p['sms'])),
            },

            f: {
                category: 'disaster_alert',
                priority: 'normal',
                title: '',
                message: '',
                target_type: 'all_farmers',
                target_id: '',
                scheduled_for: '',
            },

            open() {
                this.step = 'compose';
                this.showing = true;
            },

            label(list, key) {
                return this.lookup[list][key] || key;
            },

            audienceLabel() {
                return this.lookup.audiences[this.f.target_type] || this.f.target_type;
            },

            sendsSms() {
                return this.lookup.sms[this.f.priority] === true;
            },
        };
    }
</script>
@endpush
