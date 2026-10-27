@extends('layouts.guest')
@section('title', 'Correct My Application')
@php $hideBrandHeader = true; $wide = true; @endphp

@php
    /* CORRECTING A RETURNED APPLICATION (revision step 6)

       Not the registration wizard. That form has four steps, an account
       section and a password, and sending a farmer back through it to fix a
       barangay would mean retyping their credentials to change one field.
       This is one page, prefilled, with the credentials deliberately absent:
       the username, email and password are the account, not the
       application, and nothing here can change them.

       The association is absent too. A farmer cannot move themselves to
       another association while one is reviewing their application, because
       the association reading it would change underneath the officer.

       Everything on this page is validated by FarmerCorrectionRequest with
       the same rules the registration form uses. The browser hints below
       (required, maxlength, pattern) are a courtesy, never the rule. */

    $labelClass = 'mb-1.5 block text-sm font-medium text-slate-700';
    $inputClass = fn ($field = null) => 'w-full rounded-lg border px-3 py-2.5 text-sm focus:outline-none focus:ring-1 '
        . ($field && $errors->has($field)
            ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
            : 'border-slate-300 focus:border-green-600 focus:ring-green-600');

    /* Prefilled from what the farmer already submitted, falling back to
       whatever they typed on a failed attempt.

       array_values() is load bearing and is the same guard the registration
       form uses. A farmer who removes the middle row of three submits
       crops[0] and crops[2], so old('crops') comes back with the keys 0 and
       2 - and json_encode turns a PHP array with a gap in its keys into a
       JavaScript OBJECT, not an array. Alpine's x-for would still render it,
       but crops.length would be undefined and the Add Another Crop button
       would throw, so the form would come back from a validation error with
       its crop rows frozen. Reindexing first keeps it an array. */
    $seedCrops = array_values(old('crops', $farmer->mainCrops
        ->map(fn ($mc) => ['crop_id' => (string) $mc->crop_id, 'crop_specify' => (string) ($mc->crop_specify ?? '')])
        ->values()
        ->all()));

    $seedCrops = array_map(fn ($row) => [
        'crop_id'      => (string) ($row['crop_id'] ?? ''),
        'crop_specify' => (string) ($row['crop_specify'] ?? ''),
    ], $seedCrops);

    if (empty($seedCrops)) {
        $seedCrops = [['crop_id' => '', 'crop_specify' => '']];
    }
@endphp

@section('content')
<div x-data="{
        ownership: @js(old('ownership_type', $farmer->ownership_type)),
        crops: @js($seedCrops),
        hvcc: @js($crops->where('is_hvcc', true)->pluck('id')->map(fn ($id) => (string) $id)->values()),
        confirming: false,
        certName: '',
        certUrl: '',
        pickCert(event) {
            if (this.certUrl) { URL.revokeObjectURL(this.certUrl); }
            const file = event.target.files[0];
            this.certName = file ? file.name : '';
            this.certUrl  = file ? URL.createObjectURL(file) : '';
        },
        isHvcc(id) { return this.hvcc.includes(String(id)); },
        addCrop() { this.crops.push({ crop_id: '', crop_specify: '' }); },
        removeCrop(i) { this.crops.splice(i, 1); },
     }">

    {{-- WHAT WAS ASKED FOR, AT THE TOP OF THE FORM
         Repeated from the holding screen on purpose. A farmer who has
         scrolled into a long form should not have to go back a page to read
         what they were asked to change. --}}
    @if ($farmer->return_note)
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-red-800">What needs fixing</p>
            <p class="mt-1 text-sm text-red-700">{{ $farmer->return_note }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
            <p class="text-sm font-semibold text-red-800">Please check the following:</p>
            <ul class="mt-1.5 list-inside list-disc text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('farmer.application.resubmit') }}" enctype="multipart/form-data"
          class="rounded-2xl bg-white p-6 shadow-sm sm:p-8">
        @csrf
        @method('PUT')

        <h2 class="text-lg font-bold text-slate-800">Correct My Application</h2>
        <p class="mt-1 mb-6 text-sm text-slate-600">
            Fix what was asked for, then send it back. Everything you entered before is already filled in.
            Your username, email and password are not changed here.
        </p>

        {{-- ---------- PERSONAL INFORMATION ---------- --}}
        <p class="mb-3 border-b border-slate-200 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
            Personal Information
        </p>

        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="first_name" class="{{ $labelClass }}">First name <span class="text-red-500">*</span></label>
                <input id="first_name" type="text" name="first_name" required maxlength="100"
                       value="{{ old('first_name', $farmer->first_name) }}" class="{{ $inputClass('first_name') }}">
            </div>

            <div>
                <label for="middle_name" class="{{ $labelClass }}">Middle name</label>
                <input id="middle_name" type="text" name="middle_name" maxlength="100"
                       value="{{ old('middle_name', $farmer->middle_name) }}" class="{{ $inputClass('middle_name') }}">
            </div>

            <div>
                <label for="last_name" class="{{ $labelClass }}">Last name <span class="text-red-500">*</span></label>
                <input id="last_name" type="text" name="last_name" required maxlength="100"
                       value="{{ old('last_name', $farmer->last_name) }}" class="{{ $inputClass('last_name') }}">
            </div>

            <div>
                <label for="date_of_birth" class="{{ $labelClass }}">Date of birth <span class="text-red-500">*</span></label>
                <input id="date_of_birth" type="date" name="date_of_birth" required max="{{ now()->subDay()->toDateString() }}"
                       value="{{ old('date_of_birth', $farmer->date_of_birth?->toDateString()) }}"
                       class="{{ $inputClass('date_of_birth') }}">
            </div>

            <div>
                <label for="sex" class="{{ $labelClass }}">Sex <span class="text-red-500">*</span></label>
                <select id="sex" name="sex" required class="{{ $inputClass('sex') }}">
                    <option value="male" @selected(old('sex', $farmer->sex) === 'male')>Male</option>
                    <option value="female" @selected(old('sex', $farmer->sex) === 'female')>Female</option>
                </select>
            </div>

            <div>
                {{-- The number the office rings and the SMS alerts dial, so
                     a wrong one is worth being able to fix here. It lives on
                     the account rather than the farmer record; the
                     controller writes it there. --}}
                <label for="phone_number" class="{{ $labelClass }}">Mobile number <span class="text-red-500">*</span></label>
                <input id="phone_number" type="tel" name="phone_number" required inputmode="numeric"
                       maxlength="11" pattern="[0-9]{11}" placeholder="09171234567"
                       value="{{ old('phone_number', $farmer->user->phone_number) }}"
                       class="{{ $inputClass('phone_number') }}">
                <p class="mt-1 text-xs text-slate-500">11 digits, for example 09171234567.</p>
            </div>

            <div class="sm:col-span-2">
                <label for="address" class="{{ $labelClass }}">Complete address <span class="text-red-500">*</span></label>
                <textarea id="address" name="address" rows="2" required maxlength="1000"
                          class="{{ $inputClass('address') }}">{{ old('address', $farmer->address) }}</textarea>
            </div>

            <div>
                <label for="barangay_id" class="{{ $labelClass }}">Barangay <span class="text-red-500">*</span></label>
                <select id="barangay_id" name="barangay_id" required class="{{ $inputClass('barangay_id') }}">
                    @foreach ($barangays as $barangay)
                        <option value="{{ $barangay->id }}"
                            @selected((int) old('barangay_id', $farmer->barangay_id) === $barangay->id)>
                            {{ $barangay->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                {{-- Shown, not editable. See the note at the top of this
                     file: changing association mid-review would move the
                     application out from under the officer reading it. --}}
                <label class="{{ $labelClass }}">Farmers' Association</label>
                <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-600">
                    {{ $farmer->association->name ?? 'Not set' }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    To change this, ask the Municipal Agriculture Office.
                </p>
            </div>
        </div>

        {{-- ---------- FARM OWNERSHIP ---------- --}}
        <p class="mb-3 border-b border-slate-200 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
            Farm Ownership
        </p>

        <div class="mb-4">
            <label for="ownership_type" class="{{ $labelClass }}">Are you the land owner or a tenant? <span class="text-red-500">*</span></label>
            <select id="ownership_type" name="ownership_type" x-model="ownership" required
                    class="{{ $inputClass('ownership_type') }} sm:max-w-xs">
                <option value="land_owner">Land Owner</option>
                <option value="tenant">Tenant</option>
            </select>
        </div>

        {{-- Tenant only. x-show rather than a plugin: Alpine core only in
             this project, no x-collapse. --}}
        <div x-show="ownership === 'tenant'" x-cloak class="mb-6 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="landowner_name" class="{{ $labelClass }}">Land owner's full name <span class="text-red-500">*</span></label>
                <input id="landowner_name" type="text" name="landowner_name" maxlength="255"
                       value="{{ old('landowner_name', $farmer->landowner_name) }}"
                       class="{{ $inputClass('landowner_name') }}">
            </div>

            <div>
                <label for="landowner_contact" class="{{ $labelClass }}">Land owner's contact number</label>
                <input id="landowner_contact" type="text" name="landowner_contact" maxlength="20"
                       value="{{ old('landowner_contact', $farmer->landowner_contact) }}"
                       class="{{ $inputClass('landowner_contact') }}">
            </div>

            <div class="sm:col-span-2">
                <label for="landowner_location" class="{{ $labelClass }}">Land owner's location <span class="text-red-500">*</span></label>
                <input id="landowner_location" type="text" name="landowner_location" maxlength="255"
                       value="{{ old('landowner_location', $farmer->landowner_location) }}"
                       class="{{ $inputClass('landowner_location') }}">
            </div>
        </div>

        {{-- Land owner only --}}
        <div x-show="ownership === 'land_owner'" x-cloak class="mb-6">
            <label for="barangay_certificate" class="{{ $labelClass }}">Barangay Certificate</label>

            @if ($farmer->barangay_certificate_path)
                <p class="mb-2 text-sm text-slate-600">
                    You already uploaded one.
                    <a href="{{ $farmer->barangay_certificate_url }}" target="_blank"
                       rel="noopener" class="font-medium text-green-800 hover:underline">View it</a>.
                    Only attach a file below if you are replacing it.
                </p>
            @else
                <p class="mb-2 text-sm text-slate-600">
                    Land owners have to upload a Barangay Certificate proving they own the land.
                </p>
            @endif

            <input id="barangay_certificate" type="file" name="barangay_certificate"
                   accept=".jpg,.jpeg,.png,.pdf" @change="pickCert($event)"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm file:mr-3 file:rounded file:border-0 file:bg-green-800 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
            <p class="mt-1 text-xs text-slate-500">JPG, PNG or PDF, up to 5MB.</p>
        </div>

        {{-- ---------- FARM INFORMATION ---------- --}}
        <p class="mb-3 border-b border-slate-200 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
            Farm Information
        </p>

        <div class="mb-5 sm:max-w-xs">
            <label for="farm_size_hectares" class="{{ $labelClass }}">Farm size (hectares)</label>
            <input id="farm_size_hectares" type="number" step="0.01" min="0" name="farm_size_hectares"
                   value="{{ old('farm_size_hectares', $farmer->farm_size_hectares) }}"
                   class="{{ $inputClass('farm_size_hectares') }}">
        </div>

        <label class="{{ $labelClass }}">Main crops <span class="text-red-500">*</span></label>

        <template x-for="(crop, index) in crops" :key="index">
            <div class="mb-3 grid items-end gap-3 sm:grid-cols-[1fr_1fr_auto]">
                <select :name="`crops[${index}][crop_id]`" x-model="crop.crop_id" required
                        class="{{ $inputClass() }}">
                    <option value="">Select crop type</option>
                    @foreach ($crops as $cropOption)
                        <option value="{{ $cropOption->id }}">{{ $cropOption->name }}</option>
                    @endforeach
                </select>

                <input type="text" :name="`crops[${index}][crop_specify]`" x-model="crop.crop_specify"
                       x-show="isHvcc(crop.crop_id)" x-cloak maxlength="255"
                       placeholder="Specify crop (e.g. Ampalaya)" class="{{ $inputClass() }}">

                <button type="button" x-show="crops.length > 1" @click="removeCrop(index)"
                        class="rounded-lg border border-red-200 px-3.5 py-2.5 text-xs font-medium text-red-600 hover:bg-red-50">
                    Remove
                </button>
            </div>
        </template>

        <button type="button" @click="addCrop()"
                class="mt-1 rounded-lg border border-green-800 px-4 py-2 text-sm font-medium text-green-800 hover:bg-green-50">
            + Add Another Crop
        </button>

        {{-- ---------- REVIEW, THEN CONFIRM (revision section 91) ----------
             Submitting is not one click. The dialog says where the
             application goes next, because the farmer's reasonable
             assumption is that correcting it sends it back to whoever asked,
             and it does not: it always restarts at the association. --}}
        <div class="mt-8 flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row-reverse">
            <button type="button" @click="confirming = true"
                    class="rounded-lg bg-green-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-900">
                Review &amp; Resubmit
            </button>
            <a href="{{ route('account.pending') }}"
               class="rounded-lg border border-slate-300 px-5 py-2.5 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">
                Cancel
            </a>
        </div>

        <div x-show="confirming" x-cloak
             class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
            <div class="my-auto w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="mb-2 text-lg font-semibold text-slate-800">Resubmit your application?</h3>
                <p class="mb-4 text-sm text-slate-600">
                    Please make sure everything you changed is correct. Your corrected application goes to
                    <strong>{{ $farmer->association->name ?? 'your Farmers\' Association' }}</strong> for
                    review, and then on to the Municipal Agriculture Office.
                </p>
                {{-- The file being sent, openable, so it can be checked before
                     it goes. --}}
                <p x-show="certName" x-cloak class="mb-4 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    New Barangay Certificate:
                    <a :href="certUrl" target="_blank" rel="noopener"
                       class="font-medium text-green-800 underline hover:no-underline" x-text="certName"></a>
                </p>
                <div class="flex gap-3">
                    <button type="button" @click="confirming = false"
                            class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Back to Edit
                    </button>
                    <button type="submit"
                            class="flex-1 rounded-lg bg-green-800 px-4 py-2 text-sm font-semibold text-white hover:bg-green-900">
                        Confirm &amp; Resubmit
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
