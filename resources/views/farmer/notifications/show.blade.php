@extends('layouts.app')

@section('title', 'Notification')
@section('heading', $notification->broadcast?->title ?? 'Notification')
@section('subheading', 'From the Municipal Agriculture Office')

@section('header-actions')
    <x-ui.button variant="outline" :href="route('farmer.notifications.index')">Back to notifications</x-ui.button>
@endsection

@section('content')
@php $alert = $notification->broadcast; @endphp

<div class="space-y-5">
    <x-ui.card>
        <div class="mb-4 flex flex-wrap items-center gap-2">
            @if ($alert)
                <x-ui.badge variant="primary">{{ $alert->category_label }}</x-ui.badge>
                <x-ui.status :value="$alert->priority" />
            @endif
            <span class="text-xs text-muted-foreground">
                {{ \Illuminate\Support\Carbon::parse($notification->created_at)->format('F d, Y g:i A') }}
            </span>
        </div>

        <p class="whitespace-pre-line text-[15px] leading-relaxed text-card-foreground">
            {{ $alert?->message }}
        </p>

        <x-slot:footer>
            Sent by {{ $alert?->createdBy?->display_name ?? 'Municipal Agriculture Office' }}
        </x-slot:footer>
    </x-ui.card>

    @if ($notification->sms_status === 'pending')
        <x-ui.alert variant="info">
            A text message for this advisory is queued. SMS sending is not connected yet, so for now
            please rely on this notification.
        </x-ui.alert>
    @endif
</div>
@endsection
