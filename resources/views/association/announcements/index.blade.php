@extends('layouts.app')

@section('title', 'Announcements')
@section('heading', 'Announcements')
@section('heading-fil', 'Mga Anunsyo')
@section('subheading', 'What you tell the members of ' . $association->name . '. Only they receive it.')

@section('header-actions')
    <div class="flex flex-wrap items-center gap-2">
    <x-ui.button size="lg" variant="outline" :href="route('association.sms-history.index')">SMS History</x-ui.button>
    <x-ui.button size="lg" x-data @click="$dispatch('open-announcement-composer')">
        <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        New Announcement
    </x-ui.button>
    </div>
@endsection

@section('content')

{{--
    THE ASSOCIATION'S OWN ANNOUNCEMENTS

    Not the bell. The bell is this officer's inbox - what the office and the
    system have sent TO them. This page is the other direction: what the
    association tells its own members, and only its own members.

    Every announcement here reached the verified farmers of this association
    through their own notification bells. Nobody else can see it: not another
    association, and not the Municipal Agriculture Office, whose Announcements
    page and Archive both exclude these rows deliberately.

    The composer is two steps, compose then review, before anything is sent
    (section 91.12) - and an announcement cannot be unsent, so the review
    screen states plainly how many members will receive it and whether it is
    also going out as a text message.
--}}

<div x-data="announcementComposer()" @open-announcement-composer.window="open()">


    @if ($errors->any())
        <x-ui.alert variant="destructive" class="mb-4">{{ $errors->first() }}</x-ui.alert>
    @endif

    {{-- Who this page reaches. Stated once, at the top, rather than repeated
         on every card: it is the same audience every time, which is the whole
         point of the feature. --}}
    <div class="mb-5 flex flex-col gap-2 rounded-xl border border-border bg-accent/60 px-4 py-3
                sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-accent-foreground">
            Everything you post here goes to the
            <strong>{{ number_format($memberCount) }}</strong>
            verified {{ \Illuminate\Support\Str::plural('member', $memberCount) }}
            of {{ $association->name }}, and to nobody else.
        </p>

        <form method="GET" class="shrink-0">
            <x-ui.input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Search announcements" class="sm:w-60" />
        </form>
    </div>

    @forelse ($announcements as $announcement)
        <x-ui.card class="mb-3">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-accent px-2.5 py-0.5 text-xs font-semibold text-accent-foreground">
                            {{ $announcement->category_label }}
                        </span>

                        @if ($announcement->sends_sms)
                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800
                                         dark:bg-amber-950 dark:text-amber-300">
                                {{ $announcement->priority_label }} &middot; SMS
                            </span>
                        @else
                            <span class="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-semibold text-secondary-foreground">
                                {{ $announcement->priority_label }}
                            </span>
                        @endif

                        @if ($announcement->status === 'archived')
                            <span class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold text-muted-foreground">
                                Archived
                            </span>
                        @endif
                    </div>

                    <h2 class="text-lg font-bold text-card-foreground">{{ $announcement->title }}</h2>

                    <p class="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">
                        {{ \Illuminate\Support\Str::limit($announcement->message, 240) }}
                    </p>

                    <p class="text-xs text-muted-foreground">
                        Sent {{ $announcement->sent_at?->format('M d, Y g:i A') ?? '-' }}
                        by {{ $announcement->createdBy?->display_name ?? 'Unknown' }}
                        &middot;
                        read by {{ $announcement->read_count }} of {{ $announcement->notifications_count }}
                    </p>
                </div>

                <div class="shrink-0">
                    @if ($announcement->status === 'archived')
                        <form method="POST" action="{{ route('association.announcements.restore', $announcement) }}"
                              data-confirm="This puts the announcement back on your active list. Members keep the copy they already received either way."
                              data-confirm-title="Restore this announcement?"
                              data-confirm-detail="{{ $announcement->title }}"
                              data-confirm-action="Confirm Restore">
                            @csrf @method('PUT')
                            <button type="submit"
                                    class="h-10 w-full rounded-lg border border-input px-4 text-sm font-medium text-foreground hover:bg-muted/60">
                                Restore
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('association.announcements.archive', $announcement) }}"
                              data-confirm="This takes the announcement off your active list. Nothing is deleted, and the members who already received it keep it."
                              data-confirm-title="Archive this announcement?"
                              data-confirm-detail="{{ $announcement->title }}"
                              data-confirm-action="Confirm Archive">
                            @csrf @method('PUT')
                            <button type="submit"
                                    class="h-10 w-full rounded-lg border border-input px-4 text-sm font-medium text-foreground hover:bg-muted/60">
                                Archive
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </x-ui.card>
    @empty
        <x-ui.empty
            title="Nothing announced yet"
            message="Use New Announcement to tell your members about a meeting, a deadline or a reminder. It reaches them in the app, and by text message as well if you mark it Urgent." />
    @endforelse

    <div class="mt-5">{{ $announcements->links() }}</div>

    {{-- =====================================================================
         COMPOSER - compose, then review, then send
    ====================================================================== --}}
    <div x-show="showing" x-cloak x-transition.opacity
         style="position:fixed;top:0;right:0;bottom:0;left:0;z-index:90;background-color:rgba(0,0,0,0.4);"
         class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4 py-10">

        <div x-show="showing" x-transition.opacity.duration.150ms class="w-full max-w-xl rounded-xl bg-card shadow-xl">

            {{-- @submit.prevent on the form turns the first press into "go to
                 the review step" rather than a send; only the button on the
                 review step submits for real. --}}
            <form method="POST" action="{{ route('association.announcements.store') }}"
                  @submit.prevent="step = 'review'">
                @csrf

                <div class="flex items-center justify-between border-b border-border px-6 py-4">
                    <h3 class="text-lg font-semibold text-foreground"
                        x-text="step === 'compose' ? 'New Announcement' : 'Review Announcement'"></h3>
                    <button type="button" @click="showing = false"
                            class="rounded p-1.5 text-muted-foreground hover:bg-secondary" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
                        </svg>
                    </button>
                </div>

                {{-- ---------- Compose ---------- --}}
                <div x-show="step === 'compose'" class="space-y-5 px-6 py-5">

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="ann-category" class="mb-1.5 block text-sm font-medium text-foreground">
                                Type <span class="text-destructive">*</span>
                            </label>
                            <select id="ann-category" name="category" x-model="f.category" required
                                    class="w-full rounded-lg border border-input bg-card px-3.5 py-2.5 text-sm focus:border-primary">
                                @foreach ($categories as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="ann-priority" class="mb-1.5 block text-sm font-medium text-foreground">
                                Priority <span class="text-destructive">*</span>
                            </label>
                            <select id="ann-priority" name="priority" x-model="f.priority" required
                                    class="w-full rounded-lg border border-input bg-card px-3.5 py-2.5 text-sm focus:border-primary">
                                @foreach (\App\Models\NotificationBroadcast::selectablePriorities() as $key => $meta)
                                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-xs" :class="sendsSms() ? 'text-amber-700 dark:text-amber-400' : 'text-muted-foreground'"
                               x-text="sendsSms()
                                    ? 'Urgent / Critical also sends a text message to every member with a phone number.'
                                    : 'Delivered in the app only.'"></p>
                        </div>
                    </div>

                    <div>
                        <label for="ann-title" class="mb-1.5 block text-sm font-medium text-foreground">
                            Title <span class="text-destructive">*</span>
                        </label>
                        <input id="ann-title" type="text" name="title" x-model="f.title" required maxlength="255"
                               placeholder="e.g. General assembly on Saturday"
                               class="w-full rounded-lg border border-input bg-card px-3.5 py-2.5 text-sm focus:border-primary">
                    </div>

                    <div>
                        <label for="ann-message" class="mb-1.5 block text-sm font-medium text-foreground">
                            Message <span class="text-destructive">*</span>
                        </label>
                        <textarea id="ann-message" name="message" x-model="f.message" required rows="5" maxlength="2000"
                                  placeholder="When, where, and what members need to bring or do."
                                  class="w-full rounded-lg border border-input bg-card px-3.5 py-2.5 text-sm focus:border-primary"></textarea>
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            <span x-text="f.message.length"></span> of 2000 characters.
                        </p>
                    </div>

                    <p class="rounded-lg bg-muted px-3 py-2.5 text-xs text-muted-foreground">
                        This goes to the {{ number_format($memberCount) }} verified
                        {{ \Illuminate\Support\Str::plural('member', $memberCount) }} of {{ $association->name }}.
                        You cannot choose a different audience, and nobody outside your association receives it.
                    </p>
                </div>

                {{-- ---------- Review ---------- --}}
                <div x-show="step === 'review'" x-cloak class="px-6 py-5">
                    <p class="mb-4 text-sm text-muted-foreground">
                        Check it before it goes out. An announcement cannot be unsent.
                    </p>

                    <dl class="space-y-3 rounded-lg bg-muted p-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Type</dt>
                            <dd class="min-w-0 text-right font-medium text-foreground" x-text="label('categories', f.category)"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Priority</dt>
                            <dd class="min-w-0 text-right font-medium text-foreground" x-text="label('priorities', f.priority)"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Title</dt>
                            <dd class="min-w-0 text-right font-medium text-foreground" x-text="f.title || '-'"></dd>
                        </div>
                        <div>
                            <dt class="mb-1 text-muted-foreground">Message</dt>
                            <dd class="whitespace-pre-line rounded bg-card p-3 text-foreground" x-text="f.message || '-'"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Recipients</dt>
                            <dd class="min-w-0 text-right font-medium text-foreground">
                                {{ number_format($memberCount) }} {{ \Illuminate\Support\Str::plural('member', $memberCount) }}
                                of {{ $association->name }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Delivery</dt>
                            <dd class="min-w-0 text-right font-medium text-foreground"
                                x-text="sendsSms() ? 'In the app and by SMS' : 'In the app only'"></dd>
                        </div>
                    </dl>

                    {{-- SMS costs money per message, so the warning is loud and
                         states the number it is about to multiply by. --}}
                    <div x-show="sendsSms()" x-cloak
                         class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800
                                dark:border-amber-900 dark:bg-amber-950/60 dark:text-amber-300">
                        This priority also sends a text message to each of the {{ number_format($memberCount) }}
                        {{ \Illuminate\Support\Str::plural('member', $memberCount) }} who has a phone number on file,
                        on top of the announcement in the app.
                        @unless ($smsReady)
                            The SMS provider is not set up yet, so only the in-app announcement will go out.
                        @endunless
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
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground hover:brightness-110">
                        Review Announcement
                    </button>

                    <button type="button" x-show="step === 'review'" x-cloak @click="$root.submit()"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground hover:brightness-110">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M2.5 21L23 12 2.5 3v7l14 2-14 2v7z"/>
                        </svg>
                        <span x-text="sendsSms() ? 'Send Announcement & SMS' : 'Send Announcement'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function announcementComposer() {
        return {
            showing: false,
            step: 'compose',

            lookup: {
                categories: @json($categories),
                priorities: @json(collect(\App\Models\NotificationBroadcast::PRIORITIES)->map(fn ($p) => $p['label'])),
                sms:        @json(collect(\App\Models\NotificationBroadcast::PRIORITIES)->map(fn ($p) => $p['sms'])),
            },

            f: {
                category: 'event',
                priority: 'normal',
                title: '',
                message: '',
            },

            open() {
                this.step = 'compose';
                this.showing = true;
            },

            label(list, key) {
                return this.lookup[list][key] || key;
            },

            sendsSms() {
                return this.lookup.sms[this.f.priority] === true;
            },
        };
    }
</script>
@endpush
