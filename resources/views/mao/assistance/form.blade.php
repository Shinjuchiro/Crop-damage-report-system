@extends('layouts.app')

@section('title', $assistance ? 'Edit Assistance' : 'Add Assistance')
@section('heading', $assistance ? 'Edit Assistance' : 'Add Assistance')
@section('subheading', 'A kind of assistance the Municipal Agriculture Office can allocate to farmers.')

@php
    /* ONE PAGE, NOT TWO STEPS (Oct 2026, client decision)

       This form used to open on a step that asked whether the assistance
       was Cash or In-Kind, and everything after it was gated on that
       answer: the quantity field changed its own label, and the review
       dialog printed the type back.

       Assistance is no longer classified, and no figure is recorded against
       it, so the first step had nothing to ask and the second had nothing
       to branch on. What is left is a name, what it is for, and whether it
       can be allocated, which fits on one page.

       The reason the figures went is privacy, and it is the client's own:
       how much a named farmer received is a financial detail about an
       identifiable person, and the people who work these screens have no
       need to see it. See App\Models\Assistance for the full note. */

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
              name: @js(old('name', $assistance->name ?? '')),
              description: @js(old('description', $assistance->description ?? '')),
              disasterId: @js((string) old('disaster_id', $assistance->disaster_id ?? '')),
              cropId: @js((string) old('crop_id', $assistance->crop_id ?? '')),
              status: @js(old('status', $assistance->status ?? 'active')),
              lookup: {
                  disasters: @js($disasters->pluck('name', 'id')),
                  crops: @js($crops->pluck('name', 'id')),
              },
              label(list, id) { return this.lookup[list][id] || 'Any'; },
          }">
        @csrf
        @if ($assistance) @method('PUT') @endif

        <div class="space-y-5 rounded-xl border border-border bg-card p-6 shadow-sm">

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <div class="sm:col-span-2 xl:col-span-3">
                    <label for="name" class="{{ $labelClass }}">Assistance Name <span class="text-red-500">*</span></label>
                    <input id="name" type="text" name="name" x-model="name" required maxlength="255"
                           placeholder="e.g. Rice Seeds, Fertilizer, Farm Tools" class="{{ $inputClass }}">
                    <p class="mt-1.5 text-xs text-muted-foreground">
                        The name is what every screen shows, so make it the name the office says out loud.
                    </p>
                </div>

                <div>
                    <label for="status" class="{{ $labelClass }}">Status <span class="text-red-500">*</span></label>
                    <select id="status" name="status" x-model="status" required class="{{ $inputClass }}">
                        @foreach ($statuses as $value)
                            <option value="{{ $value }}">{{ ucfirst($value) }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-muted-foreground">Only active assistance can be allocated.</p>
                </div>

                <div class="sm:col-span-2 xl:col-span-3">
                    <label for="description" class="{{ $labelClass }}">Description</label>
                    <textarea id="description" name="description" x-model="description" rows="3" maxlength="1000"
                              placeholder="What this assistance covers" class="{{ $inputClass }}"></textarea>
                </div>

                <div>
                    <label for="disaster_id" class="{{ $labelClass }}">For Disaster Event</label>
                    <select id="disaster_id" name="disaster_id" x-model="disasterId" class="{{ $inputClass }}">
                        <option value="">Any disaster</option>
                        @foreach ($disasters as $disaster)
                            <option value="{{ $disaster->id }}">{{ $disaster->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="crop_id" class="{{ $labelClass }}">For Crop</label>
                    <select id="crop_id" name="crop_id" x-model="cropId" class="{{ $inputClass }}">
                        <option value="">Any crop</option>
                        @foreach ($crops as $crop)
                            <option value="{{ $crop->id }}">{{ $crop->name }}</option>
                        @endforeach
                    </select>
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

        {{-- Review before saving (revision section 91) --}}
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
                        <dd class="min-w-0 text-right font-medium text-foreground" x-text="name || '-'"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">For Disaster</dt>
                        <dd class="min-w-0 text-right font-medium text-foreground" x-text="label('disasters', disasterId)"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">For Crop</dt>
                        <dd class="min-w-0 text-right font-medium text-foreground" x-text="label('crops', cropId)"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">Status</dt>
                        <dd class="min-w-0 text-right font-medium capitalize text-foreground" x-text="status"></dd>
                    </div>

                    @if ($assistance)
                        <div class="border-t border-border pt-3">
                            <p class="mb-1 text-xs uppercase tracking-wide text-muted-foreground">Previously</p>
                            <p class="text-xs text-muted-foreground">
                                {{ $assistance->name }} &middot; {{ ucfirst($assistance->status) }}
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
