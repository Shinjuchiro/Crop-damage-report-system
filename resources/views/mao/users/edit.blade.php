@extends('layouts.app')

@section('title', 'Edit User')
@section('heading', 'Edit User Account')
@section('subheading', 'Change the account details. Roles cannot be switched here.')

@php
    $inputClass = 'w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring';
    $labelClass = 'mb-1.5 block text-sm font-medium text-foreground';

    $roleLabels = [
        'mao'         => 'Administrator',
        'technician'  => 'Technician',
        'association' => 'Association Officer',
        'farmer'      => 'Farmer',
    ];
@endphp

@section('content')
<div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('mao.users.update', $user) }}"
          @submit.prevent="confirm = true"
          x-data="{
              confirm: false,
              fullName: @js(old('full_name', $user->full_name ?? '')),
              username: @js(old('username', $user->username)),
              email: @js(old('email', $user->email)),
              phone: @js(old('phone_number', $user->phone_number)),
              password: '',
              associationId: @js((string) old('association_id', $user->associationOfficer?->association_id ?? '')),
              associationName() {
                  const found = @js($associations->pluck('name', 'id'));
                  return found[this.associationId] || 'Not assigned';
              },
          }">
        @csrf @method('PUT')

        <div class="space-y-5 rounded-xl border border-border bg-card p-6 shadow-sm">

            <div class="rounded-lg bg-muted px-4 py-3 text-sm">
                <span class="text-muted-foreground">Role:</span>
                <span class="font-medium text-foreground">{{ $roleLabels[$user->role] ?? ucfirst($user->role) }}</span>
                <span class="mx-2 text-muted-foreground">|</span>
                <span class="text-muted-foreground">Account status:</span>
                <span class="font-medium capitalize text-foreground">{{ $user->status }}</span>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <div>
                    <label class="{{ $labelClass }}">Full Name</label>
                    <input type="text" name="full_name" x-model="fullName" maxlength="255"
                           placeholder="Full name" class="{{ $inputClass }}">
                    @if ($user->role === 'farmer')
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            Farmer names come from the farmer profile and are shown there.
                        </p>
                    @endif
                </div>

                <div>
                    <label class="{{ $labelClass }}">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" x-model="username" required maxlength="100"
                           class="{{ $inputClass }}">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" x-model="email" required class="{{ $inputClass }}">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Phone Number <span class="text-red-500">*</span></label>
                    <input type="text" name="phone_number" x-model="phone" required maxlength="20"
                           class="{{ $inputClass }}">
                </div>

                @if ($user->role === 'association')
                    <div class="sm:col-span-2 xl:col-span-3">
                        <label class="{{ $labelClass }}">Farmers' Association <span class="text-red-500">*</span></label>
                        <select name="association_id" x-model="associationId" required class="{{ $inputClass }}">
                            <option value="">Select association</option>
                            @foreach ($associations as $association)
                                <option value="{{ $association->id }}">{{ $association->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="border-t border-border pt-5">
                <p class="mb-3 text-sm font-medium text-foreground">Reset password</p>
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label class="{{ $labelClass }}">New Password</label>
                        <input type="password" name="password" x-model="password" class="{{ $inputClass }}">
                        <p class="mt-1.5 text-xs text-muted-foreground">Leave blank to keep the current password.</p>
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Confirm New Password</label>
                        <input type="password" name="password_confirmation" class="{{ $inputClass }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('mao.users.index') }}"
               class="rounded-lg border border-input px-5 py-2.5 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                Cancel
            </a>
            <button type="submit"
                    class="flex-1 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110">
                Review Changes
            </button>
        </div>

        {{-- Review before saving --}}
        <div x-show="confirm" x-cloak class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <div class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                <h3 class="mb-1 text-lg font-semibold text-foreground">Review Changes</h3>
                <p class="mb-4 text-sm text-muted-foreground">Check the details below before saving.</p>

                <dl class="mb-5 space-y-3 rounded-lg bg-muted p-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">Full Name</dt>
                        <dd class="text-right font-bold text-foreground" x-text="fullName || 'Not set'"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">Username</dt>
                        <dd class="text-right font-medium text-foreground" x-text="username"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">Email</dt>
                        <dd class="break-all text-right font-medium text-foreground" x-text="email"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">Phone</dt>
                        <dd class="text-right font-medium text-foreground" x-text="phone"></dd>
                    </div>
                    @if ($user->role === 'association')
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Association</dt>
                            <dd class="text-right font-bold text-foreground" x-text="associationName()"></dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">Password</dt>
                        <dd class="text-right font-medium text-foreground"
                            x-text="password ? 'Will be replaced' : 'Unchanged'"></dd>
                    </div>

                    <div class="border-t border-border pt-3">
                        <p class="mb-1 text-xs uppercase tracking-wide text-muted-foreground">Previously</p>
                        <p class="text-xs text-muted-foreground">
                            {{ $user->full_name ?: 'No name' }} &middot; {{ $user->username }} &middot; {{ $user->email }}
                        </p>
                    </div>
                </dl>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button type="button" @click="confirm = false"
                            class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                        Back to Edit
                    </button>
                    <button type="button" @click="$root.submit()"
                            class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:brightness-110">
                        Confirm &amp; Save
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
