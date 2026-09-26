@extends('layouts.app')

@section('title', $assistance ? 'Edit Assistance' : 'Add Assistance')
@section('heading', $assistance ? 'Edit Assistance' : 'Add Assistance')
@section('subheading', 'A pool of assistance the MAO can later allocate to associations.')

@php
    $inputClass = 'w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring';
    $labelClass = 'mb-1.5 block text-sm font-medium text-foreground';
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

    <form method="POST"
          action="{{ $assistance ? route('mao.assistance.update', $assistance) : route('mao.assistance.store') }}"
          @submit.prevent="confirm = true"
          x-data="{
              confirm: false,
              step: @js($assistance ? 'details' : 'type'),
              name: @js(old('name', $assistance->name ?? '')),
              type: @js(old('type', $assistance->type ?? 'in_kind')),
              description: @js(old('description', $assistance->description ?? '')),
              disasterId: @js((string) old('disaster_id', $assistance->disaster_id ?? '')),
              cropId: @js((string) old('crop_id', $assistance->crop_id ?? '')),
              available: @js((string) old('available_quantity_or_amount', $assistance->available_quantity_or_amount ?? '')),
              status: @js(old('status', $assistance->status ?? 'active')),
              lookup: {
                  disasters: @js($disasters->pluck('name', 'id')),
                  crops: @js($crops->pluck('name', 'id')),
              },
              label(list, id) { return this.lookup[list][id] || 'Any'; },
          }">
        @csrf
        @if ($assistance) @method('PUT') @endif

        <input type="hidden" name="type" :value="type">

        {{-- Step 1: choose the assistance type before anything else --}}
        <div x-show="step === 'type'" x-cloak x-transition.opacity
             class="rounded-xl border border-border bg-card p-6 shadow-sm">
            <h3 class="mb-1 text-base font-semibold text-foreground">Assistance Type</h3>
            <p class="mb-5 text-sm text-muted-foreground">
                Choose the kind of assistance this pool provides. You can change this later.
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <button type="button" @click="type = 'in_kind'; step = 'details'"
                        class="rounded-xl border-2 p-5 text-left transition"
                        :class="type === 'in_kind' ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'">
                    <span class="mb-1 block text-sm font-bold text-foreground">In-Kind</span>
                    <span class="block text-xs text-muted-foreground">Seeds, fertilizer, farm tools, or other physical goods.</span>
                </button>
                <button type="button" @click="type = 'cash'; step = 'details'"
                        class="rounded-xl border-2 p-5 text-left transition"
                        :class="type === 'cash' ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/40'">
                    <span class="mb-1 block text-sm font-bold text-foreground">Cash</span>
                    <span class="block text-xs text-muted-foreground">Financial assistance disbursed directly to the recipient.</span>
                </button>
            </div>

            <a href="{{ route('mao.assistance.index') }}" class="mt-5 inline-block text-sm text-muted-foreground hover:text-foreground">
                Cancel
            </a>
        </div>

        {{-- Step 2: item details, gated on a type having been chosen --}}
        <div x-show="step === 'details'" x-cloak x-transition.opacity>

            <div class="mb-5 flex items-center justify-between rounded-lg bg-muted px-4 py-3">
                <div>
                    <span class="block text-xs uppercase tracking-wide text-muted-foreground">Assistance Type</span>
                    <span class="text-sm font-bold text-foreground" x-text="type === 'cash' ? 'Cash' : 'In-Kind'"></span>
                </div>
                <button type="button" @click="step = 'type'" class="text-sm font-medium text-primary hover:underline">
                    Change
                </button>
            </div>

        <div class="space-y-5 rounded-xl border border-border bg-card p-6 shadow-sm">

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <div class="sm:col-span-2 xl:col-span-3">
                    <label class="{{ $labelClass }}">Assistance Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" x-model="name" required maxlength="255"
                           placeholder="e.g. Rice Seeds, Financial Assistance" class="{{ $inputClass }}">
                </div>

                <div>
                    <label class="{{ $labelClass }}">Status <span class="text-red-500">*</span></label>
                    <select name="status" x-model="status" required class="{{ $inputClass }}">
                        @foreach ($statuses as $value)
                            <option value="{{ $value }}">{{ ucfirst($value) }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-muted-foreground">Only active assistance can be allocated.</p>
                </div>

                <div class="sm:col-span-2 xl:col-span-3">
                    <label class="{{ $labelClass }}">Description</label>
                    <textarea name="description" x-model="description" rows="3" maxlength="1000"
                              placeholder="What this assistance covers" class="{{ $inputClass }}"></textarea>
                </div>

                <div>
                    <label class="{{ $labelClass }}">For Disaster Event</label>
                    <select name="disaster_id" x-model="disasterId" class="{{ $inputClass }}">
                        <option value="">Any disaster</option>
                        @foreach ($disasters as $disaster)
                            <option value="{{ $disaster->id }}">{{ $disaster->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="{{ $labelClass }}">For Crop</label>
                    <select name="crop_id" x-model="cropId" class="{{ $inputClass }}">
                        <option value="">Any crop</option>
                        @foreach ($crops as $crop)
                            <option value="{{ $crop->id }}">{{ $crop->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-2 xl:col-span-3 sm:max-w-xs">
                    <label class="{{ $labelClass }}">
                        <span x-show="type !== 'cash'">Available Quantity</span>
                        <span x-show="type === 'cash'" x-cloak>Available Amount</span>
                    </label>
                    <input type="number" step="0.01" min="0" name="available_quantity_or_amount"
                           x-model="available" placeholder="Leave blank if not tracked" class="{{ $inputClass }}">
                    <p class="mt-1.5 text-xs text-muted-foreground">
                        The total pool. Allocations are subtracted from it.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('mao.assistance.index') }}"
               class="rounded-lg border border-input px-5 py-2.5 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                Cancel
            </a>
            <button type="submit"
                    class="flex-1 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:brightness-110">
                {{ $assistance ? 'Review Changes' : 'Review & Add Assistance' }}
            </button>
        </div>

        </div>{{-- /step: details --}}

        {{-- Review before saving --}}
        <div x-show="confirm" x-cloak x-transition.opacity
             class="fixed inset-0 z-[90] flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <div x-show="confirm" x-transition class="my-auto w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                <h3 class="mb-1 text-lg font-semibold text-foreground">
                    {{ $assistance ? 'Review Changes' : 'Confirm New Assistance' }}
                </h3>
                <p class="mb-4 text-sm text-muted-foreground">Check the details before saving.</p>

                <dl class="mb-5 space-y-3 rounded-lg bg-muted p-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">Name</dt>
                        <dd class="text-right font-medium text-foreground" x-text="name || '-'"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">Type</dt>
                        <dd class="text-right font-medium text-foreground" x-text="type === 'cash' ? 'Cash' : 'In-Kind'"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">For Disaster</dt>
                        <dd class="text-right font-medium text-foreground" x-text="label('disasters', disasterId)"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">For Crop</dt>
                        <dd class="text-right font-medium text-foreground" x-text="label('crops', cropId)"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">Available</dt>
                        <dd class="text-right font-medium text-foreground" x-text="available || 'Not tracked'"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">Status</dt>
                        <dd class="text-right font-medium capitalize text-foreground" x-text="status"></dd>
                    </div>

                    @if ($assistance)
                        <div class="border-t border-border pt-3">
                            <p class="mb-1 text-xs uppercase tracking-wide text-muted-foreground">Previously</p>
                            <p class="text-xs text-muted-foreground">
                                {{ $assistance->name }} &middot;
                                {{ $assistance->type === 'cash' ? 'Cash' : 'In-Kind' }} &middot;
                                {{ ucfirst($assistance->status) }}
                            </p>
                        </div>
                    @endif
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
