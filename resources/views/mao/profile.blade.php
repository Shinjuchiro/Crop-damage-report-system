@extends('layouts.app')

@section('title', 'My Profile')
@section('heading', 'My Profile')
@section('subheading', 'Your account photo, basic details and account settings.')

@section('content')
<div class="max-w-2xl space-y-4">

    @if ($errors->any())
        <x-ui.alert variant="destructive">{{ $errors->first() }}</x-ui.alert>
    @endif

    <x-ui.card>
        <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-left">
            <x-profile-photo :user="$user"
                :update-route="route('mao.profile.photo.update')"
                :remove-route="route('mao.profile.photo.remove')" />

            <div class="min-w-0">
                <p class="text-xl font-bold text-card-foreground">{{ $user->display_name }}</p>
                <p class="text-sm font-medium text-primary">Municipal Admin</p>
                <p class="mt-1 text-sm text-muted-foreground">{{ $user->email }}</p>
                <p class="text-sm text-muted-foreground">{{ $user->phone_number ?: 'No phone number on file' }}</p>
            </div>
        </div>
    </x-ui.card>

    @include('settings.account', ['user' => $user])
</div>
@endsection
