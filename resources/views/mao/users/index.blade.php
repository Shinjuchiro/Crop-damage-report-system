@extends('layouts.app')

@section('title', 'User Management')
@section('hideHeading', true)

@php
    $statusBadges = [
        'active'   => 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        'pending'  => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'inactive' => 'bg-secondary text-foreground',
        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    ];

    $roleLabels = [
        'mao'         => 'Administrator',
        'technician'  => 'Technician',
        'association' => 'Association Officer',
        'farmer'      => 'Farmer',
    ];
@endphp

@section('content')
<div x-data="{ addOpen: false, archiving: null }">
<x-ui.card title="Users">
    <x-slot:actions>
        {{-- Kept as its own relative/absolute dropdown rather than the
             card's normal single-button actions slot, since this one opens
             a small menu (Technician / Association Officer) instead of
             going straight to a route. --}}
        <div class="relative">
            <x-ui.button @click="addOpen = ! addOpen">
                <span class="text-base leading-none">+</span> Add User
            </x-ui.button>

            <div x-show="addOpen" x-cloak @click.outside="addOpen = false"
                 class="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-xl border border-border bg-card shadow-lg">
                <a href="{{ route('mao.users.technicians.create') }}"
                   class="block px-4 py-3 text-sm text-foreground hover:bg-muted/60">
                    <span class="font-medium">Technician</span>
                    <span class="mt-0.5 block text-xs text-muted-foreground">Conducts field inspections</span>
                </a>
                <a href="{{ route('mao.users.association-officers.create') }}"
                   class="block border-t border-border px-4 py-3 text-sm text-foreground hover:bg-muted/60">
                    <span class="font-medium">Association Officer</span>
                    <span class="mt-0.5 block text-xs text-muted-foreground">Monitors members and distributes assistance</span>
                </a>
                <p class="border-t border-border bg-muted px-4 py-2.5 text-xs text-muted-foreground">
                    Farmers create their own accounts through registration.
                </p>
            </div>
        </div>
    </x-slot:actions>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Filters. Stacked and full-width on a phone, one row from sm up.
         No Search button: the text field submits on Enter and the two
         selects submit as soon as a choice is made, the same way every
         other filter bar in the system now works. --}}
    <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <x-ui.select name="role" placeholder="All roles" onchange="this.form.submit()"
                     :options="$roleLabels" :selected="request('role')" class="sm:w-48" />

        <x-ui.select name="status" placeholder="All statuses" onchange="this.form.submit()"
                     :options="collect(['active', 'pending', 'inactive', 'rejected'])->mapWithKeys(fn ($v) => [$v => ucfirst($v)])"
                     :selected="request('status')" class="sm:w-48" />

        <x-ui.input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email"
                    class="sm:min-w-[14rem] sm:flex-1" />

        @if (request()->hasAny(['search', 'role', 'status']))
            <a href="{{ route('mao.users.index') }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b-2 border-border text-base font-bold text-foreground">
                    <th class="px-4 py-3">User ID</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border text-sm">
                @forelse ($users as $user)
                    <tr class="hover:bg-muted/60">
                        <td class="px-4 py-4 text-foreground">
                            USR-{{ str_pad($user->id, 3, '0', STR_PAD_LEFT) }}
                        </td>
                        <td class="px-4 py-4">
                            <p class="font-medium text-foreground">{{ $user->display_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ $user->email }}</p>
                        </td>
                        <td class="px-4 py-4 text-foreground">
                            {{ $roleLabels[$user->role] ?? ucfirst($user->role) }}
                            @if ($user->role === 'association' && $user->associationOfficer?->association)
                                <span class="block text-xs text-muted-foreground">
                                    {{ $user->associationOfficer->association->name }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            <span class="inline-flex rounded px-3 py-1 text-xs font-medium {{ $statusBadges[$user->status] ?? $statusBadges['pending'] }}">
                                {{ ucfirst($user->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center justify-center gap-2">
                                @if ($user->role === 'mao' && $user->id !== auth()->id())
                                    <span class="text-xs text-muted-foreground">Protected account</span>
                                @else
                                    <a href="{{ route('mao.users.edit', $user) }}"
                                       class="rounded bg-green-100 px-5 py-1.5 text-xs font-semibold text-green-900 hover:bg-green-200">
                                        Edit
                                    </a>

                                    @if ($user->id !== auth()->id() && $user->role !== 'mao')
                                        <button type="button"
                                                @click="archiving = {
                                                    id: {{ $user->id }},
                                                    name: @js($user->display_name),
                                                    action: @js($user->status === 'inactive' ? 'active' : 'inactive')
                                                }"
                                                class="rounded bg-amber-100 px-4 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-200">
                                            {{ $user->status === 'inactive' ? 'Restore' : 'Archive' }}
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-14 text-center">
                            <p class="text-sm font-medium text-muted-foreground">No users match these filters</p>
                            <p class="mt-1 text-xs text-muted-foreground">Try clearing the search or filters.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 border-t border-border pt-5">{{ $users->links() }}</div>
</x-ui.card>

    {{-- Archive / restore confirmation --}}
    <div x-show="archiving" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
            <h3 class="mb-2 text-lg font-semibold text-foreground"
                x-text="archiving?.action === 'inactive' ? 'Archive this user?' : 'Restore this user?'"></h3>

            <p class="mb-5 text-sm text-muted-foreground">
                <template x-if="archiving?.action === 'inactive'">
                    <span>
                        <strong x-text="archiving?.name"></strong> will no longer be able to sign in.
                        Their records are kept and the account can be restored later.
                    </span>
                </template>
                <template x-if="archiving?.action === 'active'">
                    <span><strong x-text="archiving?.name"></strong> will be able to sign in again.</span>
                </template>
            </p>

            <div class="flex gap-3">
                <button type="button" @click="archiving = null"
                        class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                    Cancel
                </button>
                <form method="POST" :action="`{{ url('mao/users') }}/${archiving?.id}/status`" class="flex-1">
                    @csrf @method('PUT')
                    <input type="hidden" name="status" :value="archiving?.action">
                    <button type="submit"
                            class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                        Confirm
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
