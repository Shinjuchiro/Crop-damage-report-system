@extends('layouts.app')

@section('title', 'My Profile')
@section('heading', 'My Profile')
@section('subheading', 'Your account photo, basic details and account settings.')

@section('content')
<div class="space-y-4">

    @if ($errors->any())
        <x-ui.alert variant="destructive">{{ $errors->first() }}</x-ui.alert>
    @endif

    <x-ui.card>
        <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-left">
            <x-profile-photo :user="$user"
                :update-route="route('technician.profile.photo.update')"
                :remove-route="route('technician.profile.photo.remove')" />

            <div class="min-w-0 flex-1">
                <p class="text-xl font-bold text-card-foreground">{{ $user->display_name }}</p>
                <p class="text-sm font-medium text-primary">Technician</p>
            </div>
        </div>

        {{-- Same shape as the farmer's profile: the identity on top, the
             facts in a row of columns that grows with the screen, so a wide
             window is used instead of left empty (no dead space). --}}
        <dl class="mt-5 grid gap-4 border-t border-border pt-5 text-center sm:grid-cols-2 sm:text-left lg:grid-cols-4">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Username</dt>
                <dd class="mt-1 break-words text-sm font-medium">{{ $user->username }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Email</dt>
                <dd class="mt-1 break-all text-sm font-medium">{{ $user->email ?: 'Not provided' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Phone Number</dt>
                <dd class="mt-1 text-sm font-medium">{{ $user->phone_number ?: 'No phone number on file' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Member Since</dt>
                <dd class="mt-1 text-sm font-medium">{{ $user->created_at?->format('F Y') ?? '-' }}</dd>
            </div>
        </dl>
    </x-ui.card>

    @include('settings.account', ['user' => $user])
</div>
@endsection
