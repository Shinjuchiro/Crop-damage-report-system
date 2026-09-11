@extends('layouts.app')
@section('title', 'Review Application')
@section('heading', 'Review Farmer Registration')
@section('subheading', 'Verify the information before approving or rejecting')

@section('content')
<div x-data="{ confirmAction: null }" class="space-y-6">

    {{-- Personal information --}}
    <div class="rounded-xl border border-border bg-card p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Personal Information</h3>
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-muted-foreground">Full Name</dt><dd class="font-medium text-foreground">{{ $farmer->full_name }}</dd></div>
            <div><dt class="text-muted-foreground">Date of Birth</dt><dd class="font-medium text-foreground">{{ $farmer->date_of_birth?->format('M d, Y') ?? '—' }}</dd></div>
            <div><dt class="text-muted-foreground">Sex</dt><dd class="font-medium capitalize text-foreground">{{ $farmer->sex }}</dd></div>
            <div><dt class="text-muted-foreground">Contact Number</dt><dd class="font-medium text-foreground">{{ $farmer->user->phone_number }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-muted-foreground">Address</dt><dd class="font-medium text-foreground">{{ $farmer->address }}</dd></div>
            <div><dt class="text-muted-foreground">Barangay</dt><dd class="font-medium text-foreground">{{ $farmer->barangay->name ?? '—' }}</dd></div>
            <div><dt class="text-muted-foreground">Association</dt><dd class="font-medium text-foreground">{{ $farmer->association->name ?? '—' }}</dd></div>
        </dl>
    </div>

    {{-- Account --}}
    <div class="rounded-xl border border-border bg-card p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Account Information</h3>
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-muted-foreground">Username</dt><dd class="font-medium text-foreground">{{ $farmer->user->username }}</dd></div>
            <div><dt class="text-muted-foreground">Email</dt><dd class="font-medium text-foreground">{{ $farmer->user->email }}</dd></div>
        </dl>
        {{-- Password is never displayed --}}
    </div>

    {{-- Farm ownership --}}
    <div class="rounded-xl border border-border bg-card p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Farm Ownership</h3>
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">Farmer Type</dt>
                <dd class="font-medium text-foreground">{{ $farmer->ownership_type === 'land_owner' ? 'Land Owner' : 'Tenant' }}</dd>
            </div>

            @if ($farmer->ownership_type === 'tenant')
                <div><dt class="text-muted-foreground">Land Owner Name</dt><dd class="font-medium text-foreground">{{ $farmer->landowner_name ?? '—' }}</dd></div>
                <div><dt class="text-muted-foreground">Land Owner Contact</dt><dd class="font-medium text-foreground">{{ $farmer->landowner_contact ?? '—' }}</dd></div>
                <div><dt class="text-muted-foreground">Land Owner Location</dt><dd class="font-medium text-foreground">{{ $farmer->landowner_location ?? '—' }}</dd></div>
            @else
                <div class="sm:col-span-2">
                    <dt class="text-muted-foreground">Barangay Certificate</dt>
                    <dd>
                        @if ($farmer->barangay_certificate_path)
                            <a href="{{ asset('storage/' . $farmer->barangay_certificate_path) }}" target="_blank"
                               class="font-medium text-green-800 hover:underline">View Barangay Certificate</a>
                        @else
                            <span class="text-muted-foreground">Not provided</span>
                        @endif
                    </dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Farm information --}}
    <div class="rounded-xl border border-border bg-card p-6">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-green-800">Farm Information</h3>
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">Farm Size</dt>
                <dd class="font-medium text-foreground">{{ $farmer->farm_size_hectares ? $farmer->farm_size_hectares . ' ha' : '—' }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Main Crops</dt>
                <dd class="font-medium text-foreground">
                    @forelse ($farmer->mainCrops as $mc)
                        {{ $mc->crop->name }}{{ $mc->crop_specify ? " ({$mc->crop_specify})" : '' }}@if(!$loop->last), @endif
                    @empty — @endforelse
                </dd>
            </div>
        </dl>
    </div>

    {{-- Actions --}}
    @if ($farmer->user->status === 'pending')
        <div class="rounded-xl border border-border bg-card p-6">
            <h3 class="mb-1 text-sm font-semibold text-foreground">Review Decision</h3>
            <p class="mb-4 text-sm text-muted-foreground">Please confirm you have reviewed all information above.</p>

            <div class="flex flex-col gap-3 sm:flex-row">
                <button @click="confirmAction = 'approve'"
                        class="rounded-lg bg-green-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-900">
                    Approve Registration
                </button>
                <button @click="confirmAction = 'reject'"
                        class="rounded-lg border border-red-300 px-5 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">
                    Reject Registration
                </button>
                <a href="{{ route('mao.membership-applications.index') }}"
                   class="rounded-lg border border-input px-5 py-2.5 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                    Back to List
                </a>
            </div>
        </div>

        {{-- Approve confirmation --}}
        <div x-show="confirmAction === 'approve'" x-cloak
             class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-card p-6">
                <h3 class="mb-2 text-lg font-semibold text-foreground">Confirm Approval</h3>
                <p class="mb-5 text-sm text-muted-foreground">
                    Are you sure you want to approve <strong>{{ $farmer->full_name }}</strong>?
                    The farmer will gain full access to the system.
                </p>
                <div class="flex gap-3">
                    <button @click="confirmAction = null"
                            class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                        Cancel
                    </button>
                    <form method="POST" action="{{ route('mao.membership-applications.approve', $farmer) }}" class="flex-1">
                        @csrf @method('PUT')
                        <button class="w-full rounded-lg bg-green-800 px-4 py-2 text-sm font-semibold text-white hover:bg-green-900">
                            Confirm Approval
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Reject confirmation --}}
        <div x-show="confirmAction === 'reject'" x-cloak
             class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-card p-6">
                <h3 class="mb-2 text-lg font-semibold text-foreground">Confirm Rejection</h3>
                <p class="mb-4 text-sm text-muted-foreground">
                    Are you sure you want to reject this registration?
                </p>
                <form method="POST" action="{{ route('mao.membership-applications.reject', $farmer) }}">
                    @csrf @method('PUT')
                    <label class="mb-1 block text-sm font-medium text-foreground">Reason (optional)</label>
                    <textarea name="rejection_reason" rows="3"
                              class="mb-4 w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600"></textarea>
                    <div class="flex gap-3">
                        <button type="button" @click="confirmAction = null"
                                class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">
                            Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-border bg-card p-6">
            <p class="text-sm text-muted-foreground">
                This application has already been reviewed. Current status:
                <span class="font-semibold capitalize">{{ $farmer->user->status }}</span>
            </p>
            <a href="{{ route('mao.membership-applications.index') }}"
               class="mt-3 inline-block rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                Back to List
            </a>
        </div>
    @endif
</div>
@endsection
