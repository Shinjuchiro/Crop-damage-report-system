@extends('layouts.app')

@section('title', 'My Profile')
@section('heading', 'My Profile')
@section('subheading', 'Your account photo and basic details.')

@section('content')
<div class="max-w-2xl space-y-4">

    <x-ui.card>
        <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-left">
            <x-profile-photo :user="$user"
                :update-route="route('association.profile.photo.update')"
                :remove-route="route('association.profile.photo.remove')" />

            <div class="min-w-0">
                <p class="text-xl font-bold text-card-foreground">{{ $user->display_name }}</p>
                <p class="text-sm font-medium text-primary">Association Officer</p>
                <p class="mt-1 text-sm text-muted-foreground">{{ $user->email }}</p>
                <p class="text-sm text-muted-foreground">{{ $user->phone_number ?: 'No phone number on file' }}</p>
            </div>
        </div>
    </x-ui.card>

    <x-ui.alert variant="info" title="Looking for something else?">
        Your login email, phone number and password are managed on the
        <a href="{{ route('association.settings.index') }}" class="font-semibold underline">Account Settings</a> page.
    </x-ui.alert>
</div>
@endsection
