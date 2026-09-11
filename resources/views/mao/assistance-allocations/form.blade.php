@extends('layouts.app')

@section('title', 'Add Allocation')
@section('heading', 'Allocate Assistance')
@section('subheading', "Assistance always goes to a Farmers' Association, which then distributes it to its members.")

@php
    $inputClass = 'w-full rounded-lg border border-input bg-card px-3.5 py-2.5 text-sm focus:border-primary focus:ring-1 focus:ring-ring';
    $labelClass = 'mb-1.5 block text-sm font-medium text-foreground';

    // The sentinel the Assistance dropdown uses for "make a new one". Read
    // from the controller so the two can never drift apart.
    $newValue = \App\Http\Controllers\MAO\AssistanceAllocationController::NEW_ASSISTANCE;
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
        An empty assistance catalogue used to block this whole page and send
        the officer off to Settings to create an item, then walk back here.
        It no longer does: the dropdown below has a "create a new one" option
        that defines the item inline and saves it to the catalogue on the way
        through. Only a missing association can stop you now, and that is
        genuinely a different job.
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
                  assistanceId: @js((string) old('assistance_id', '')),
                  associationId: @js((string) old('association_id', '')),
                  disasterId: @js((string) old('disaster_id', '')),
                  cropId: @js((string) old('crop_id', '')),
                  description: @js(old('in_kind_description', '')),
                  quantity: @js((string) old('allocated_quantity', '')),
                  remarks: @js(old('remarks', '')),

                  /* The inline new item */
                  newName: @js(old('new_assistance_name', '')),
                  newType: @js(old('new_assistance_type', 'in_kind')),
                  newDescription: @js(old('new_assistance_description', '')),
                  newAvailable: @js((string) old('new_assistance_available', '')),

                  lookup: {
                      assistances: @js($assistances->pluck('name', 'id')),
                      types: @js($assistances->pluck('type', 'id')),
                      associations: @js($associations->pluck('name', 'id')),
                      disasters: @js($disasters->pluck('name', 'id')),
                      crops: @js($crops->pluck('name', 'id')),
                  },

                  /* Are we defining a new catalogue item on this form? */
                  makingNew() { return this.assistanceId === @js($newValue); },

                  /* Cash or in kind, whichever source applies */
                  isCash() {
                      return this.makingNew()
                          ? this.newType === 'cash'
                          : this.lookup.types[this.assistanceId] === 'cash';
                  },

                  assistanceName() {
                      if (this.makingNew()) {
                          return (this.newName || 'Unnamed') + ' (new)';
                      }
                      return this.lookup.assistances[this.assistanceId] || 'Not specified';
                  },

                  label(list, id) { return this.lookup[list][id] || 'Not specified'; },

                  /* Enough to submit? Stops the review opening on a half filled form. */
                  ready() {
                      if (! this.assistanceId || ! this.associationId) return false;
                      if (this.makingNew() && ! this.newName.trim()) return false;
                      return true;
                  },
              }">
            @csrf

            <div class="space-y-5 rounded-xl border border-border bg-card p-6 shadow-sm">

                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label for="assistance_id" class="{{ $labelClass }}">
                            Assistance <span class="text-destructive">*</span>
                        </label>
                        <select id="assistance_id" name="assistance_id" x-model="assistanceId" required
                                class="{{ $inputClass }}">
                            <option value="">Select assistance</option>

                            {{-- First, so it is found without hunting to the
                                 bottom of a long list. --}}
                            <option value="{{ $newValue }}">+ Create a new assistance item</option>

                            @if ($assistances->isNotEmpty())
                                <optgroup label="Already in your list">
                                    @foreach ($assistances as $assistance)
                                        <option value="{{ $assistance->id }}"
                                                @selected(old('assistance_id') == $assistance->id)>
                                            {{ $assistance->name }} ({{ $assistance->type === 'cash' ? 'Cash' : 'In-Kind' }})
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>

                        <p class="mt-1.5 text-xs text-muted-foreground">
                            @if ($assistances->isEmpty())
                                Nothing in your list yet. Choose "Create a new assistance item" and define it here.
                            @else
                                <a href="{{ route('mao.assistance.index') }}" class="font-medium text-primary hover:underline">
                                    Manage the full list
                                </a>
                                to rename, edit or close items.
                            @endif
                        </p>
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
                     THE NEW ITEM, only when one is being made
                ========================================================== --}}
                <div x-show="makingNew()" x-cloak x-transition
                     class="rounded-xl border border-primary/30 bg-accent p-5">

                    <p class="text-sm font-semibold text-accent-foreground">New assistance item</p>
                    <p class="mt-0.5 text-xs text-accent-foreground/80">
                        This is saved to your assistance list as well, so next time you can just pick it.
                    </p>

                    <div class="mt-4 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="sm:col-span-2">
                            <label for="new_assistance_name" class="{{ $labelClass }}">
                                Name <span class="text-destructive">*</span>
                            </label>
                            <input id="new_assistance_name" type="text" name="new_assistance_name" maxlength="255"
                                   x-model="newName" value="{{ old('new_assistance_name') }}"
                                   placeholder="e.g. Certified Rice Seed, Fuel Subsidy"
                                   class="{{ $inputClass }}">
                        </div>

                        <div>
                            <label for="new_assistance_type" class="{{ $labelClass }}">
                                Type <span class="text-destructive">*</span>
                            </label>
                            <select id="new_assistance_type" name="new_assistance_type" x-model="newType"
                                    class="{{ $inputClass }}">
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" @selected(old('new_assistance_type') === $type)>
                                        {{ $type === 'cash' ? 'Cash' : 'In-Kind' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="new_assistance_available" class="{{ $labelClass }}">
                                Total available
                                <span class="font-normal text-muted-foreground">(optional)</span>
                            </label>
                            <input id="new_assistance_available" type="number" step="0.01" min="0"
                                   name="new_assistance_available" x-model="newAvailable"
                                   value="{{ old('new_assistance_available') }}"
                                   placeholder="0.00" class="{{ $inputClass }}">
                            <p class="mt-1.5 text-xs text-muted-foreground">
                                The whole pool, across every association.
                            </p>
                        </div>

                        <div class="sm:col-span-2 xl:col-span-4">
                            <label for="new_assistance_description" class="{{ $labelClass }}">
                                Description <span class="font-normal text-muted-foreground">(optional)</span>
                            </label>
                            <input id="new_assistance_description" type="text" name="new_assistance_description"
                                   maxlength="1000" x-model="newDescription"
                                   value="{{ old('new_assistance_description') }}"
                                   placeholder="What this assistance is, in a sentence"
                                   class="{{ $inputClass }}">
                        </div>
                    </div>
                </div>

                {{-- =========================================================
                     THIS PARTICULAR ALLOCATION
                ========================================================== --}}
                <div class="grid gap-5 border-t border-border pt-5 sm:grid-cols-2 xl:grid-cols-3">

                    <div class="sm:col-span-2 xl:col-span-3">
                        <label for="in_kind_description" class="{{ $labelClass }}">Description</label>
                        <input id="in_kind_description" type="text" name="in_kind_description" x-model="description"
                               maxlength="255" value="{{ old('in_kind_description') }}"
                               placeholder="e.g. 10 bags of certified rice seed" class="{{ $inputClass }}">
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            What exactly is going to this association. Leave blank to use the item's own description.
                        </p>
                    </div>

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

                        {{-- Only worth saying when something is actually being
                             added to the catalogue. --}}
                        <div class="flex justify-between gap-4" x-show="makingNew()">
                            <dt class="shrink-0 text-muted-foreground">New item</dt>
                            <dd class="text-right font-medium text-primary">
                                Will be added to your assistance list
                            </dd>
                        </div>

                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Association</dt>
                            <dd class="text-right font-medium" x-text="label('associations', associationId)"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Type</dt>
                            <dd class="text-right font-medium" x-text="isCash() ? 'Cash' : 'In-Kind'"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="shrink-0 text-muted-foreground">Description</dt>
                            <dd class="text-right font-medium" x-text="description || 'Not specified'"></dd>
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
