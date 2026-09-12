@extends('layouts.app')

@section('title', $notification->broadcast?->title ?? 'Notification')
@section('heading', $notification->broadcast?->title ?? 'Notification')
@section('subheading', 'From the Municipal Agriculture Office')

@section('header-actions')
    <x-ui.button variant="outline" :href="route('technician.notifications.index')">
        Back to notifications
    </x-ui.button>
@endsection

@section('content')

@php $alert = $notification->broadcast; @endphp

<x-ui.card>
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.badge variant="primary">{{ $alert?->category_label ?? 'Announcement' }}</x-ui.badge>

        @if ($alert?->priority && $alert->priority !== 'normal')
            <x-ui.status :value="$alert->priority" />
        @endif

        <span class="text-xs text-muted-foreground">
            {{ $notification->created_at?->format('F d, Y g:i A') }}
            @if ($alert?->createdBy)
                &middot; sent by {{ $alert->createdBy->display_name }}
            @endif
        </span>
    </div>

    <div class="mt-4 whitespace-pre-line text-base leading-relaxed">
        {{ $alert?->message }}
    </div>
</x-ui.card>
@endsection
