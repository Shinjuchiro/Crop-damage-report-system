@extends('layouts.app')

@section('title', $crop ? 'Edit Crop' : 'Add Crop')
@section('heading', $crop ? 'Edit Crop' : 'Add Crop')
@section('subheading', $crop ? 'Review your changes before saving.' : 'Add a crop type farmers can select.')

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
          action="{{ $crop ? route('mao.crops.update', $crop) : route('mao.crops.store') }}"
          @submit.prevent="confirm = true"
          x-data="{
              confirm: false,
              name: @js(old('name', $crop->name ?? '')),
              hvcc: @js((bool) old('is_hvcc', $crop->is_hvcc ?? false)),
          }">
        @csrf
        @if ($crop) @method('PUT') @endif

        <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
            <div class="mb-5">
                <label class="mb-1.5 block text-sm font-medium text-foreground">
                    Crop Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" x-model="name" required maxlength="100"
                       placeholder="e.g. Rice, Corn, HVCC"
                       class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
            </div>

            <label class="flex items-start gap-3 rounded-lg bg-muted p-4 text-sm">
                <input type="checkbox" name="is_hvcc" value="1" x-model="hvcc"
                       class="mt-0.5 h-4 w-4 rounded border-input text-green-700 focus:ring-green-600">
                <span>
                    <span class="block font-medium text-foreground">High Value Commercial Crop (HVCC)</span>
                    <span class="mt-0.5 block text-xs text-muted-foreground">
                        When ticked, farmers who choose this crop are asked to specify which crop it is
                        (for example Ampalaya, Eggplant or Mango).
                    </span>
                </span>
            </label>
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('mao.crops.index') }}"
               class="rounded-lg border border-input px-5 py-2.5 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                Cancel
            </a>
            <button type="submit"
                    class="flex-1 rounded-lg bg-green-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-900">
                {{ $crop ? 'Review Changes' : 'Review & Add Crop' }}
            </button>
        </div>

        {{-- Review before saving --}}
        <div x-show="confirm" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                <h3 class="mb-1 text-lg font-semibold text-foreground">
                    {{ $crop ? 'Review Changes' : 'Confirm New Crop' }}
                </h3>
                <p class="mb-4 text-sm text-muted-foreground">
                    Please check the details below before saving.
                </p>

                <dl class="mb-5 space-y-3 rounded-lg bg-muted p-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Crop Name</dt>
                        <dd class="text-right font-medium text-foreground" x-text="name || '-'"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">HVCC</dt>
                        <dd class="text-right font-medium text-foreground" x-text="hvcc ? 'Yes' : 'No'"></dd>
                    </div>

                    @if ($crop)
                        <div class="border-t border-border pt-3">
                            <p class="mb-1 text-xs uppercase tracking-wide text-muted-foreground">Previously</p>
                            <p class="text-xs text-muted-foreground">
                                {{ $crop->name }} &middot; HVCC: {{ $crop->is_hvcc ? 'Yes' : 'No' }}
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
