@extends('layouts.app')

@section('title', 'Add Allocation')
@section('heading', 'Allocate Assistance')
@section('subheading', "Assistance always goes to a Farmers' Association, which then distributes it to its members.")

@php
    $inputClass = 'w-full rounded-lg border border-input bg-card px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring';
    $labelClass = 'mb-1.5 block text-sm font-medium text-foreground';

    // The sentinel the In-Kind Item dropdown uses for "not one of these -
    // let me describe it". Read from the controller so the two can never
    // drift apart.
    $otherInKind = \App\Http\Controllers\MAO\AssistanceAllocationController::OTHER_IN_KIND;
    $inKindOptions = $assistances->where('type', 'in_kind')->values();
@endphp

@section('content')
<div>

    @if ($errors->any())
        <x-ui.alert variant="destructive" title="Please check the form" class="mb-4">
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{--
        Sept 2026: this no longer waits on the assistance catalogue at all.
        Cash needs no catalogue item, and In-Kind can always fall back to
        "Other" with its own typed description - so only a missing
        association can stop you here.
    --}}
    @if ($associations->isEmpty())
        <x-ui.alert variant="warning" title="No Farmers' Association exists yet">
            Assistance is always allocated to an association, so one has to exist first.
            <span class="mt-2 block">
                <x-ui.button size="sm" :href="route('mao.associations.create')">Add an association</x-ui.button>
            </span>
        </x-ui.alert>
    @else
        <form method="POST" action="{{ route('mao.assistance-allocations.store') }}"
              @submit.prevent="confirm = true"
              x-data="{
                  confirm: false,
                  assistanceType: @js(old('assistance_type', '')),
                  inKindItem: @js(old('in_kind_item', '')),
                  inKindDescription: @js(old('in_kind_description', '')),
                  associationId: @js((string) old('association_id', '')),
                  disasterId: @js((string) old('disaster_id', '')),
                  cropId: @js((string) old('crop_id', '')),
                  quantity: @js((string) old('allocated_quantity', '')),
                  remarks: @js(old('remarks', '')),

                  lookup: {
                      inKindNames: @js($inKindOptions->pluck('name', 'id')),
                      associations: @js($associations->pluck('name', 'id')),
                      disasters: @js($disasters->pluck('name', 'id')),
                      crops: @js($crops->pluck('name', 'id')),
                  },

                  isCash() { return this.assistanceType === 'cash'; },
                  isOther() { return this.assistanceType === 'in_kind' && this.inKindItem === @js($otherInKind); },

                  assistanceName() {
                      if (this.assistanceType === 'cash') return 'Cash';
                      if (this.isOther()) return this.inKindDescription || 'Not specified';
                      return this.lookup.inKindNames[this.inKindItem] || 'Not specified';
                  },

                  label(list, id) { return this.lookup[list][id] || 'Not specified'; },

                  /* Enough to submit? Stops the review opening on a half filled form. */
                  ready() {
                      if (! this.assistanceType || ! this.associationId) return false;
                      if (this.assistanceType === 'in_kind') {
                          if (! this.inKindItem) return false;
                          if (this.isOther() && ! this.inKindDescription.trim()) return false;
                      }
                      return true;
                  },
              }">
            @csrf

            <div class="space-y-5 rounded-xl border border-border bg-card p-6 shadow-sm">

                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label for="assistance_type" class="{{ $labelClass }}">
                            Assistance Type <span class="text-destructive">*</span>
                        </label>
                        <select id="assistance_type" name="assistance_type" x-model="assistanceType"
                                @change="inKindItem = ''; inKindDescription = ''" required
                                class="{{ $inputClass }}">
                            <option value="">Select type</option>
                            <option value="cash" @selected(old('assistance_type') === 'cash')>Cash</option>
                            <option value="in_kind" @selected(old('assistance_type') === 'in_kind')>In-Kind</option>
                        </select>
                    </div>

                    <div x-show="assistanceType === 'in_kind'" x-cloak x-transition>
                        <label for="in_kind_item" class="{{ $labelClass }}">
                            In-Kind Item <span class="text-destructive">*</span>
                        </label>
                        <select id="in_kind_item" name="in_kind_item" x-model="inKindItem" required
                                class="{{ $inputClass }}">
                            <option value="">Select item</option>
                            @foreach ($inKindOptions as $item)
                                <option value="{{ $item->id }}" @selected(old('in_kind_item') == $item->id)>
                                    {{ $item->name }}
                                </option>
                            @endforeach
                            <option value="{{ $otherInKind }}" @selected(old('in_kind_item') === $otherInKind)>
                                Other (describe below)
                            </option>
                        </select>

                        <p class="mt-1.5 text-xs text-muted-foreground">
                            <a href="{{ route('mao.assistance.index') }}" class="font-medium text-primary hover:underline">
                                Manage the full list
                            </a>
                            to rename, edit or close items.
                        </p>
                    </div>

                    <div class="sm:col-span-2 xl:col-span-3" x-show="isOther()" x-cloak x-transition>
                        <label for="in_kind_description" class="{{ $labelClass }}">
                            Describe this item <span class="text-destructive">*</span>
                        </label>
                        <input id="in_kind_description" type="text" name="in_kind_description" maxlength="255" required
                               x-model="inKindDescription" value="{{ old('in_kind_description') }}"
                               placeholder="e.g. Certified Rice Seed, Fuel Subsidy"
                               class="{{ $inputClass }}">
                    </div>

                    <div>
                        <label for="association_id" class="{{ $labelClass }}">
                            Farmers' Association <span class="text-destructive">*</span>
                        </label>
                        <select id="association_id" name="association_id" x-model="associationId" required
                                class="{{ $inputClass }}">
                            <option value="">Select association</option>
                            @foreach ($associations as $association)
                                <option value="{{ $association->id }}"
                                        @selected(old('association_id') == $association->id)>
                                    {{ $association->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="disaster_id" class="{{ $labelClass }}">Disaster Event</label>
                        <select id="disaster_id" name="disaster_id" x-model="disasterId" class="{{ $inputClass }}">
                            <option value="">Not tied to a specific event</option>
                            @foreach ($disasters as $disaster)
                                <option value="{{ $disaster->id }}" @selected(old('disaster_id') == $disaster->id)>
                                    {{ $disaster->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="crop_id" class="{{ $labelClass }}">Crop</label>
                        <select id="crop_id" name="crop_id" x-model="cropId" class="{{ $inputClass }}">
                            <option value="">Not crop specific</option>
                            @foreach ($crops as $crop)
                                <option value="{{ $crop->id }}" @selected(old('crop_id') == $crop->id)>
                                    {{ $crop->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- =========================================================
                     THIS PARTICULAR ALLOCATION
                ========================================================== --}}
                <div class="grid gap-5 border-t border-border pt-5 sm:grid-cols-2 xl:grid-cols-3">

                    <div>
                        <label for="allocated_quantity" class="{{ $labelClass }}">
                            <span x-show="! isCash()">Quantity Allocated</span>
                            <span x-show="isCash()" x-cloak>Amount Allocated</span>
                        </label>
                        <input id="allocated_quantity" type="number" step="0.01" min="0" name="allocated_quantity"
                               x-model="quantity" value="{{ old('allocated_quantity') }}"
                               placeholder="0.00" class="{{ $inputClass }}">
                        <p class="mt-1.5 text-xs text-muted-foreground"
                           x-text="isCash() ? 'Peso amount set aside for this association.' : 'Bags, sacks, kilos, whichever unit you use.'"></p>
                    </div>

                    <div>
                        <label for="remarks" class="{{ $labelClass }}">Remarks</label>
                        <input id="remarks" type="text" name="remarks" x-model="remarks" maxlength="1000"
                               value="{{ old('remarks') }}" placeholder="Optional note" class="{{ $inputClass }}">
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <a href="{{ route('mao.assistance-allocations.index') }}"
                   class="rounded-lg border border-input px-5 py-2.5 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                    Cancel
                </a>
                <button type="submit" x-bind:disabled="! ready()"
                        class="flex-1 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground
                               hover:brightness-110 disabled:pointer-events-none disabled:opacity-50">
                    Review Allocation
                </button>
            </div>

            {{-- =============================================================
                 REVIEW BEFORE SAVING (section 91.8)

                 z-[90] is the page-modal layer: above the sidebar and any open
                 dropdown, below the global confirmation dialog. It can
                 never end up underneath something else on the page the way the
                 old z-50 could.
            ============================================================== --}}
            <div x-show="confirm" x-cloak x-transition.opacity
                 class="fixed inset-0 z-[90] flex items-center justify-center overflow-y-auto p-4">

                <div class="absolute inset-0 bg-slate-900/60" @click="confirm = false" aria-hidden="true"></div>

                <div x-show="confirm" x-transition
                     class="relative w-full max-w-md rounded-xl bg-card p-6 shadow-xl">
                    <h3 class="mb-1 text-lg font-semibold">Review Assistance Allocation</h3>
                    <p class="mb-4 text-sm text-muted-foreground">Check the details before allocating.</p>

                    <dl class="mb-5 max-h-[45vh] space-y-3 overflow-y-auto rounded-lg bg-muted p-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Assistance</dt>
                            <dd class="text-right font-medium" x-text="assistanceName()"></dd>
                        </div>

                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Association</dt>
                            <dd class="text-right font-bold" x-text="label('associations', associationId)"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Type</dt>
                            <dd class="text-right font-medium" x-text="isCash() ? 'Cash' : 'In-Kind'"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground" x-text="isCash() ? 'Amount' : 'Quantity'"></dt>
                            <dd class="text-right font-medium" x-text="quantity || 'Not specified'"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Disaster</dt>
                            <dd class="text-right font-medium" x-text="label('disasters', disasterId)"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Date</dt>
                            <dd class="text-right font-medium">{{ now()->format('F d, Y') }}</dd>
                        </div>
                    </dl>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button type="button" @click="confirm = false"
                                class="flex-1 rounded-lg border border-input px-4 py-2 text-sm font-medium text-foreground hover:bg-muted/60">
                            Back to Edit
                        </button>
                        {{-- $root is the form, because x-data sits on the form
                             element. form.submit() bypasses the @submit.prevent
                             above, which is what we want here. --}}
                        <button type="button" @click="$root.submit()"
                                class="flex-1 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:brightness-110">
                            Confirm Allocation
                        </button>
                    </div>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
