@extends('layouts.app')

@section('title', 'SMS History')
@section('heading', 'SMS History')
@section('subheading', 'Every text the system has sent or tried to send for Urgent and Critical alerts.')

@section('header-actions')
    <x-ui.button variant="outline" :href="route('mao.notifications.index')">Back to Alerts</x-ui.button>
@endsection

@section('content')
<div class="space-y-4">

    {{-- Counts

         THE "DELIVERED" CARD WAS REMOVED (Oct 2026)

         Nothing in this system ever sets sms_status to 'delivered'. The
         only place the column is written is
         NotificationBroadcast::sendSms(), which records 'sent' when the
         provider accepts the request and 'failed' when it does not.
         Knowing a text actually reached a handset needs a delivery callback
         from the provider, and there is no endpoint for one.

         So the card read "Delivered: 0" forever, sitting next to
         "Sent: 40". Read together, those two numbers say forty texts were
         sent and none arrived, which is both false and alarming, on the one
         screen the office would check after sending an urgent disaster
         advisory. Revision section 13 is explicit: "Do not claim delivery
         unless confirmed by the relevant service." A permanently empty
         Delivered counter is that claim in reverse, and just as wrong.

         'delivered' is still a valid value: it stays in the column's enum
         and in x-ui.status, so if a provider callback is ever added, rows
         will render correctly and this card can come back with something
         behind it. It is the DISPLAY of a number nothing produces that was
         the problem, not the status itself. --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <x-ui.card>
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Sent</p>
            <p class="mt-1 text-2xl font-bold text-foreground">{{ $counts['sent'] }}</p>
            <p class="mt-1 text-xs text-muted-foreground">Accepted by the SMS provider</p>
        </x-ui.card>
        <x-ui.card>
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Pending</p>
            <p class="mt-1 text-2xl font-bold text-foreground">{{ $counts['pending'] }}</p>
            <p class="mt-1 text-xs text-muted-foreground">Queued, not handed over yet</p>
        </x-ui.card>
        <x-ui.card>
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Failed</p>
            <p class="mt-1 text-2xl font-bold text-foreground">{{ $counts['failed'] }}</p>
            <p class="mt-1 text-xs text-muted-foreground">The provider rejected the request</p>
        </x-ui.card>
    </div>

    {{-- Filters + table --}}
    <x-ui.card :padded="false">
        <div class="border-b border-border px-4 pt-4 sm:px-5 sm:pt-5">
            <x-ui.filter-bar :fields="['status', 'priority', 'search']">
                {{-- 'delivered' is not offered: nothing sets it, so choosing
                     it returned an empty list every time. See the note on
                     the cards above. --}}
                <x-ui.select name="status" placeholder="All statuses" onchange="this.form.submit()"
                             :options="collect(['pending', 'sent', 'failed'])->mapWithKeys(fn ($s) => [$s => ucfirst($s)])"
                             :selected="request('status')" class="sm:w-40" />

                <x-ui.select name="priority" placeholder="All priorities" onchange="this.form.submit()"
                             :options="collect($priorities)->filter(fn ($meta) => $meta['sms'])->map(fn ($meta) => $meta['label'])"
                             :selected="request('priority')" class="sm:w-40" />

                <x-ui.input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Recipient or alert title" class="sm:min-w-[12rem] sm:flex-1" />
            </x-ui.filter-bar>
        </div>

        @if ($history->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        <tr>
                            {{-- "Alert", not "Message" (Oct 2026). The column
                                 printed the whole SMS body under the title.
                                 The body is office-written text that is
                                 already on the Alerts page in full, so
                                 repeating it here was revision section 3.1's
                                 "duplicate information already shown
                                 elsewhere" AND its "long descriptions or
                                 notes" in one cell. The truncate class only
                                 clipped it visually; the full text was still
                                 in the page source. --}}
                            <th class="px-4 py-3 sm:px-5">Date</th>
                            <th class="px-4 py-3 sm:px-5">Recipient</th>
                            <th class="px-4 py-3 sm:px-5">Alert</th>
                            <th class="px-4 py-3 sm:px-5">Priority</th>
                            <th class="px-4 py-3 sm:px-5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($history as $entry)
                            <tr class="hover:bg-muted/40">
                                <td class="whitespace-nowrap px-4 py-3 sm:px-5">
                                    {{ $entry->created_at?->format('M d, Y g:i A') }}
                                </td>
                                <td class="px-4 py-3 sm:px-5">
                                    <p class="font-bold text-foreground">{{ $entry->user?->display_name ?? 'Unknown' }}</p>
                                    {{-- Masked, per proposal section 91.11 and revision
                                         section 3.1. See User::getMaskedPhoneNumberAttribute. --}}
                                    <p class="text-xs text-muted-foreground">{{ $entry->user?->masked_phone_number ?: 'No phone on file' }}</p>
                                </td>
                                <td class="max-w-xs px-4 py-3 sm:px-5">
                                    <p class="truncate font-medium text-foreground">{{ $entry->broadcast?->title }}</p>
                                </td>
                                <td class="px-4 py-3 sm:px-5">
                                    <x-ui.status :value="$entry->broadcast?->priority" />
                                </td>
                                <td class="px-4 py-3 sm:px-5">
                                    <x-ui.status :value="$entry->sms_status" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 sm:px-5">{{ $history->links() }}</div>
        @else
            <div class="px-6 py-16 text-center">
                <p class="text-sm font-medium text-muted-foreground">No SMS activity yet</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Urgent and Critical alerts that reach a recipient with a phone number on file will show up here.
                </p>
            </div>
        @endif
    </x-ui.card>
</div>
@endsection
