@extends('layouts.app')

@section('title', 'SMS History')
@section('heading', 'SMS History')
@section('subheading', 'Every text the system has sent or tried to send for Urgent and Critical alerts.')

@section('header-actions')
    <x-ui.button variant="outline" :href="route('mao.notifications.index')">Back to Alerts</x-ui.button>
@endsection

@section('content')
<div class="space-y-4">

    {{-- Counts --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.card>
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Sent</p>
            <p class="mt-1 text-2xl font-bold text-foreground">{{ $counts['sent'] }}</p>
        </x-ui.card>
        <x-ui.card>
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Delivered</p>
            <p class="mt-1 text-2xl font-bold text-foreground">{{ $counts['delivered'] }}</p>
        </x-ui.card>
        <x-ui.card>
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Pending</p>
            <p class="mt-1 text-2xl font-bold text-foreground">{{ $counts['pending'] }}</p>
        </x-ui.card>
        <x-ui.card>
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Failed</p>
            <p class="mt-1 text-2xl font-bold text-foreground">{{ $counts['failed'] }}</p>
        </x-ui.card>
    </div>

    {{-- Filters + table --}}
    <x-ui.card :padded="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3 sm:px-5">
            <div>
                <label class="mb-1 block text-xs font-medium text-muted-foreground">Status</label>
                <select name="status" onchange="this.form.submit()"
                        class="rounded-lg border border-input px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach (['pending', 'sent', 'delivered', 'failed'] as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-muted-foreground">Priority</label>
                <select name="priority" onchange="this.form.submit()"
                        class="rounded-lg border border-input px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach ($priorities as $key => $meta)
                        @if ($meta['sms'])
                            <option value="{{ $key }}" @selected(request('priority') === $key)>{{ $meta['label'] }}</option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div class="min-w-[12rem] flex-1">
                <label class="mb-1 block text-xs font-medium text-muted-foreground">Search</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Recipient or alert title"
                       class="w-full rounded-lg border border-input px-3 py-2 text-sm">
            </div>

            <x-ui.button type="submit" size="sm">Filter</x-ui.button>
            @if (request()->hasAny(['status', 'priority', 'search']))
                <x-ui.button variant="outline" size="sm" :href="route('mao.sms-history.index')">Clear</x-ui.button>
            @endif
        </form>

        @if ($history->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 sm:px-5">Date</th>
                            <th class="px-4 py-3 sm:px-5">Recipient</th>
                            <th class="px-4 py-3 sm:px-5">Message</th>
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
                                    <p class="font-medium text-foreground">{{ $entry->user?->display_name ?? 'Unknown' }}</p>
                                    <p class="text-xs text-muted-foreground">{{ $entry->user?->phone_number ?: 'No phone on file' }}</p>
                                </td>
                                <td class="max-w-xs px-4 py-3 sm:px-5">
                                    <p class="truncate font-medium text-foreground">{{ $entry->broadcast?->title }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ $entry->broadcast?->message }}</p>
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
