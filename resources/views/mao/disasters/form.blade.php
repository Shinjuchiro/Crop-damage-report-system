@extends('layouts.app')

@section('title', $disaster ? 'Edit Disaster Event' : 'Add Disaster Event')
@section('heading', $disaster ? 'Edit Disaster Event' : 'Add Disaster Event')
@section('subheading', 'Farmers select these events when reporting crop damage.')

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
          action="{{ $disaster ? route('mao.disasters.update', $disaster) : route('mao.disasters.store') }}"
          @submit.prevent="confirm = true"
          x-data="{
              confirm: false,
              type: @js(old('type', $disaster->type ?? '')),
              name: @js(old('name', $disaster->name ?? '')),
              dateStart: @js(old('date_start', $disaster?->date_start?->toDateString() ?? '')),
              dateEnd: @js(old('date_end', $disaster?->date_end?->toDateString() ?? '')),
              typeLabel() {
                  if (! this.type) return '-';
                  return this.type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
              },
          }">
        @csrf
        @if ($disaster) @method('PUT') @endif

        <div class="space-y-5 rounded-xl border border-border bg-card p-6 shadow-sm">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-foreground">
                    Disaster Type <span class="text-red-500">*</span>
                </label>
                <select name="type" x-model="type" required
                        class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
                    <option value="">Select type</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-foreground">
                    Event Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" x-model="name" required maxlength="255"
                       placeholder="e.g. Typhoon Kristine"
                       class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
            </div>

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">
                        Start Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="date_start" x-model="dateStart" required
                           class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">End Date</label>
                    <input type="date" name="date_end" x-model="dateEnd" :min="dateStart"
                           class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
                    <p class="mt-1.5 text-xs text-muted-foreground">Leave blank for a single-day event.</p>
                </div>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('mao.disasters.index') }}"
               class="rounded-lg border border-input px-5 py-2.5 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                Cancel
            </a>
            <button type="submit"
                    class="flex-1 rounded-lg bg-green-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-900">
                {{ $disaster ? 'Review Changes' : 'Review & Add Event' }}
            </button>
        </div>

        {{-- Review before saving --}}
        <div x-show="confirm" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                <h3 class="mb-1 text-lg font-semibold text-foreground">
                    {{ $disaster ? 'Review Changes' : 'Confirm New Disaster Event' }}
                </h3>
                <p class="mb-4 text-sm text-muted-foreground">Please check the details below before saving.</p>

                <dl class="mb-5 space-y-3 rounded-lg bg-muted p-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Type</dt>
                        <dd class="text-right font-medium text-foreground" x-text="typeLabel()"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Event Name</dt>
                        <dd class="text-right font-medium text-foreground" x-text="name || '-'"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Period</dt>
                        <dd class="text-right font-medium text-foreground"
                            x-text="dateStart ? (dateEnd ? dateStart + ' to ' + dateEnd : dateStart) : '-'"></dd>
                    </div>

                    @if ($disaster)
                        <div class="border-t border-border pt-3">
                            <p class="mb-1 text-xs uppercase tracking-wide text-muted-foreground">Previously</p>
                            <p class="text-xs text-muted-foreground">
                                {{ ucwords(str_replace('_', ' ', $disaster->type)) }} &middot; {{ $disaster->name }}
                                &middot; {{ $disaster->date_start?->format('M d, Y') }}@if ($disaster->date_end) to {{ $disaster->date_end->format('M d, Y') }}@endif
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
                            class="flex-1 rounded-lg bg-green-800 px-4 py-2 text-sm font-semibold text-white hover:bg-green-900">
                        Confirm &amp; Save
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
