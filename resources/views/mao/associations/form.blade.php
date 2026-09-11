@extends('layouts.app')

@section('title', $association ? 'Edit Association' : 'Add Association')
@section('heading', $association ? 'Edit Farmers\' Association' : 'Add Farmers\' Association')
@section('subheading', 'Farmers choose their association when they register.')

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
          action="{{ $association ? route('mao.associations.update', $association) : route('mao.associations.store') }}"
          @submit.prevent="confirm = true"
          x-data="{
              confirm: false,
              name: @js(old('name', $association->name ?? '')),
              barangayId: @js((string) old('barangay_id', $association->barangay_id ?? '')),
              description: @js(old('description', $association->description ?? '')),
              barangayName() {
                  const list = @js($barangays->pluck('name', 'id'));
                  return list[this.barangayId] || 'Not set';
              },
          }">
        @csrf
        @if ($association) @method('PUT') @endif

        <div class="space-y-5 rounded-xl border border-border bg-card p-6 shadow-sm">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-foreground">
                    Association Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" x-model="name" required maxlength="255"
                       placeholder="e.g. Samahang Magsasaka ng Calibuyo"
                       class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
            </div>

            <div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">Office Barangay</label>
                    <select name="barangay_id" x-model="barangayId" class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
                        <option value="">Not set</option>
                        @foreach ($barangays as $barangay)
                            <option value="{{ $barangay->id }}">{{ $barangay->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-muted-foreground">
                        Where the association's office sits. This is where it appears on the map.
                    </p>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-foreground">Description</label>
                <textarea name="description" x-model="description" rows="3" maxlength="1000"
                          placeholder="Short description of the association"
                          class="w-full rounded-lg border border-input px-3.5 py-2.5 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600"></textarea>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('mao.associations.index') }}"
               class="rounded-lg border border-input px-5 py-2.5 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                Cancel
            </a>
            <button type="submit"
                    class="flex-1 rounded-lg bg-green-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-900">
                {{ $association ? 'Review Changes' : 'Review & Add Association' }}
            </button>
        </div>

        {{-- Review before saving --}}
        <div x-show="confirm" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                <h3 class="mb-1 text-lg font-semibold text-foreground">
                    {{ $association ? 'Review Changes' : 'Confirm New Association' }}
                </h3>
                <p class="mb-4 text-sm text-muted-foreground">Please check the details below before saving.</p>

                <dl class="mb-5 space-y-3 rounded-lg bg-muted p-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">Name</dt>
                        <dd class="text-right font-medium text-foreground" x-text="name || '-'"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">Office Barangay</dt>
                        <dd class="text-right font-medium text-foreground" x-text="barangayName()"></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="shrink-0 text-muted-foreground">Description</dt>
                        <dd class="text-right font-medium text-foreground" x-text="description || 'Not provided'"></dd>
                    </div>

                    @if ($association)
                        <div class="border-t border-border pt-3">
                            <p class="mb-1 text-xs uppercase tracking-wide text-muted-foreground">Previously</p>
                            <p class="text-xs text-muted-foreground">
                                {{ $association->name }}
                                @if ($association->barangay) &middot; {{ $association->barangay->name }} @endif
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
