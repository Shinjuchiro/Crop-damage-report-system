@extends('layouts.auth')
@section('title', 'Create an Account')

@php
    // If the server rejected the submission, reopen the step that holds the first error.
    $stepOneFields = ['first_name', 'middle_name', 'last_name', 'date_of_birth', 'sex', 'phone_number'];
    $stepTwoFields = ['username', 'email', 'password', 'password_confirmation'];

    $startStep = 1;
    if ($errors->any()) {
        $errorKeys = array_map(fn ($key) => explode('.', $key)[0], $errors->keys());

        if (array_intersect($errorKeys, $stepOneFields)) {
            $startStep = 1;
        } elseif (array_intersect($errorKeys, $stepTwoFields)) {
            $startStep = 2;
        } else {
            $startStep = 3;
        }
    }

    // Repopulate the crop repeater after a failed submission
    $oldCrops = collect(old('crops', []))->values()->map(fn ($crop) => [
        'crop_id'      => (string) ($crop['crop_id'] ?? ''),
        'crop_specify' => (string) ($crop['crop_specify'] ?? ''),
    ])->all();

    if (empty($oldCrops)) {
        $oldCrops = [['crop_id' => '', 'crop_specify' => '']];
    }

    $steps = [
        1 => 'Personal information',
        2 => 'Account Details',
        3 => 'Location & Farm',
        4 => 'Review',
    ];

    $inputClass = 'w-full rounded-lg border border-input px-3.5 py-2.5 text-sm text-foreground placeholder-slate-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-ring/20';
    $labelClass = 'mb-1.5 block text-sm font-medium text-foreground';
@endphp

@section('content')
{{-- On a phone this is just a stacked column, unchanged. From lg up, the
     whole screen gets a dark green frame (padding + gap) and the two panels
     become separate rounded cards floating inside it, rather than sitting
     edge to edge. --}}
<div class="flex min-h-screen flex-col lg:flex-row lg:gap-4 lg:bg-[#0d3d1b] lg:p-4">

    {{-- =====================================================================
         LEFT PANEL - branding and illustration
    ====================================================================== --}}
    {{-- Same office photo as Login/Forgot Password's mobile banner, dimmed
         further behind a gradient since this is a small header strip on a
         phone, not a full-bleed background. From lg up, the photo goes to
         full opacity behind the desktop login screen's flat tint instead -
         same two-panel wizard layout either way, just the treatment of the
         background photo changes at the breakpoint. --}}
    <div class="relative flex shrink-0 flex-col overflow-hidden bg-[#0d3d1b] px-5 py-6 sm:px-8
                lg:w-[30%] lg:max-w-[420px] lg:rounded-2xl lg:px-10 lg:py-10">

        <img src="{{ asset('images/fitsc-office.jpg') }}" alt=""
             class="absolute inset-0 h-full w-full object-cover opacity-40 lg:opacity-100" aria-hidden="true">
        <div class="absolute inset-0 bg-gradient-to-b from-[#0d3d1b]/80 to-[#0d3d1b]/95 lg:hidden" aria-hidden="true"></div>
        <div class="absolute inset-0 hidden bg-[#0d3d1b]/70 lg:block" aria-hidden="true"></div>

        <div class="relative flex items-center gap-4">
            <img src="{{ asset('images/tanza-seal.png') }}" alt="Seal of the Municipality of Tanza, Cavite"
                 class="h-16 w-16 shrink-0 lg:h-20 lg:w-20">
            <div>
                <p class="text-base font-bold leading-tight text-white lg:text-lg">
                    Farmers Information<br class="hidden lg:block">
                    and Technology<br class="hidden lg:block">
                    Services Center
                </p>
                <p class="mt-1.5 text-sm font-bold text-[#7ddc8f]">Municipality of Tanza</p>
            </div>
        </div>

        <div class="relative mt-10 hidden lg:block">
            <h2 class="text-3xl font-bold leading-tight text-white">
                Create your account
                <span class="text-[#7ddc8f]">and help build a stronger farming community.</span>
            </h2>
        </div>

    </div>

    {{-- =====================================================================
         RIGHT PANEL - the registration wizard
    ====================================================================== --}}
    <div class="flex-1 px-5 py-8 sm:px-8 lg:overflow-y-auto lg:rounded-2xl lg:bg-card lg:px-12 lg:py-10 lg:shadow-xl">
        <div class="mx-auto max-w-4xl" x-data="farmerRegistration()">

            <h1 class="text-2xl font-bold text-foreground sm:text-3xl">Create an Account</h1>
            <p class="mt-1 text-base text-muted-foreground">Fill in your details to get started.</p>

            {{-- ===================== STEPPER ===================== --}}
            <ol class="my-8 flex items-start">
                @foreach ($steps as $number => $label)
                    <li class="flex flex-1 items-start {{ $number < count($steps) ? '' : 'flex-none' }}">
                        <div class="flex flex-col items-center">
                            <button type="button" @click="goTo({{ $number }})"
                                    class="flex h-11 w-11 items-center justify-center rounded-full border-2 text-sm font-semibold transition"
                                    :class="step === {{ $number }}
                                        ? 'border-primary bg-primary text-white'
                                        : (step > {{ $number }}
                                            ? 'border-primary bg-card text-primary cursor-pointer'
                                            : 'border-input bg-card text-muted-foreground cursor-default')">
                                {{ $number }}
                            </button>
                            <span class="mt-2 w-24 text-center text-xs sm:w-28 sm:text-sm"
                                  :class="step === {{ $number }} ? 'font-semibold text-primary' : 'text-muted-foreground'">
                                {{ $label }}
                            </span>
                        </div>

                        @if ($number < count($steps))
                            <div class="mt-5 h-0.5 flex-1"
                                 :class="step > {{ $number }} ? 'bg-primary' : 'bg-secondary'"></div>
                        @endif
                    </li>
                @endforeach
            </ol>

            {{-- ===================== SERVER ERRORS ===================== --}}
            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
                    <p class="mb-1 font-semibold">Please correct the following:</p>
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- ===================== FORM ===================== --}}
            <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data"
                  novalidate @submit="onSubmit($event)">
                @csrf

                {{-- Client-side step message --}}
                <div x-show="stepError" x-cloak
                     class="mb-5 rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/60 px-4 py-3 text-sm text-amber-800">
                    <span x-text="stepError"></span>
                </div>

                {{-- ============================================================
                     STEP 1 - PERSONAL INFORMATION
                ============================================================= --}}
                <div x-show="step === 1" x-ref="step1">
                    <div class="mb-5 flex items-center gap-2.5">
                        <svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" stroke-width="1.7"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M15 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M8.5 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM19 8v6M22 11h-6"/>
                        </svg>
                        <h3 class="text-lg font-semibold text-primary">Personal Information</h3>
                    </div>

                    <div class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                        <div>
                            <label class="{{ $labelClass }}">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" x-model="f.first_name" required
                                   placeholder="Please enter your first name" class="{{ $inputClass }}">
                        </div>

                        <div>
                            <label class="{{ $labelClass }}">Middle Name</label>
                            <input type="text" name="middle_name" x-model="f.middle_name"
                                   placeholder="Optional" class="{{ $inputClass }}">
                        </div>

                        <div>
                            <label class="{{ $labelClass }}">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" x-model="f.last_name" required
                                   placeholder="Please enter your last name" class="{{ $inputClass }}">
                        </div>

                        <div>
                            <label class="{{ $labelClass }}">Date of Birth <span class="text-red-500">*</span></label>
                            <input type="date" name="date_of_birth" x-model="f.date_of_birth" required
                                   max="{{ now()->subDay()->toDateString() }}" class="{{ $inputClass }}">
                        </div>

                        <div>
                            <label class="{{ $labelClass }}">Sex <span class="text-red-500">*</span></label>
                            <select name="sex" x-model="f.sex" required class="{{ $inputClass }}">
                                <option value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>

                        <div>
                            <label class="{{ $labelClass }}">Phone Number <span class="text-red-500">*</span></label>
                            <input type="text" name="phone_number" x-model="f.phone_number" required
                                   placeholder="09XXXXXXXXX" class="{{ $inputClass }}">
                        </div>
                    </div>
                </div>

                {{-- ============================================================
                     STEP 2 - ACCOUNT DETAILS
                ============================================================= --}}
                <div x-show="step === 2" x-cloak x-ref="step2">
                    <div class="mb-5 flex items-center gap-2.5">
                        <svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" stroke-width="1.7"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <rect x="4" y="10" width="16" height="10" rx="2"/>
                            <path d="M8 10V7a4 4 0 118 0v3"/>
                        </svg>
                        <h3 class="text-lg font-semibold text-primary">Account Information</h3>
                    </div>

                    <div class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                        <div>
                            <label class="{{ $labelClass }}">Username <span class="text-red-500">*</span></label>
                            <input type="text" name="username" x-model="f.username" required
                                   placeholder="Choose a username" class="{{ $inputClass }}">
                        </div>

                        <div>
                            <label class="{{ $labelClass }}">Email Address <span class="text-red-500">*</span></label>
                            <input type="email" name="email" x-model="f.email" required
                                   placeholder="Please enter your email" class="{{ $inputClass }}">
                        </div>

                        <div x-data="{ show: false }">
                            <label class="{{ $labelClass }}">Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input :type="show ? 'text' : 'password'" name="password" x-model="f.password" required
                                       placeholder="Create password" class="{{ $inputClass }} pr-11">
                                <button type="button" @click="show = !show"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-muted-foreground hover:text-muted-foreground">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"
                                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <path x-show="!show" d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.4 5.2A9.5 9.5 0 0112 5c5 0 9 4.5 9 7 0 .9-.6 2.1-1.6 3.2M6.2 6.5C4 8 2 10.4 2 12c0 2.5 4 7 10 7 1.4 0 2.7-.3 3.8-.7"/>
                                        <path x-show="show" x-cloak d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/>
                                        <circle x-show="show" x-cloak cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                            <p class="mt-1.5 text-xs text-muted-foreground">At least 8 characters, with letters and numbers.</p>
                        </div>

                        <div x-data="{ show: false }">
                            <label class="{{ $labelClass }}">Confirm Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input :type="show ? 'text' : 'password'" name="password_confirmation"
                                       x-model="f.password_confirmation" required
                                       placeholder="Confirm your password" class="{{ $inputClass }} pr-11">
                                <button type="button" @click="show = !show"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-muted-foreground hover:text-muted-foreground">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"
                                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <path x-show="!show" d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.4 5.2A9.5 9.5 0 0112 5c5 0 9 4.5 9 7 0 .9-.6 2.1-1.6 3.2M6.2 6.5C4 8 2 10.4 2 12c0 2.5 4 7 10 7 1.4 0 2.7-.3 3.8-.7"/>
                                        <path x-show="show" x-cloak d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/>
                                        <circle x-show="show" x-cloak cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============================================================
                     STEP 3 - LOCATION AND FARM
                ============================================================= --}}
                <div x-show="step === 3" x-cloak x-ref="step3" class="space-y-9">

                    {{-- Location --}}
                    <div>
                        <div class="mb-5 flex items-center gap-2.5">
                            <svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11z"/>
                                <circle cx="12" cy="10" r="2.5"/>
                            </svg>
                            <h3 class="text-lg font-semibold text-primary">Location</h3>
                        </div>

                        <div class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                            <div class="sm:col-span-2 xl:col-span-3">
                                <label class="{{ $labelClass }}">Complete Address <span class="text-red-500">*</span></label>
                                <textarea name="address" x-model="f.address" rows="2" required
                                          placeholder="Purok / Street, Barangay" class="{{ $inputClass }}"></textarea>
                            </div>

                            <div>
                                <label class="{{ $labelClass }}">Barangay <span class="text-red-500">*</span></label>
                                <select name="barangay_id" x-model="f.barangay_id" required class="{{ $inputClass }}">
                                    <option value="">Select Barangay</option>
                                    @foreach ($barangays as $barangay)
                                        <option value="{{ $barangay->id }}">{{ $barangay->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="{{ $labelClass }}">Farmers' Association <span class="text-red-500">*</span></label>
                                <select name="association_id" x-model="f.association_id" required class="{{ $inputClass }}">
                                    <option value="">Select Association</option>
                                    @foreach ($associations as $association)
                                        <option value="{{ $association->id }}">{{ $association->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Farm ownership --}}
                    <div>
                        <div class="mb-2 flex items-center gap-2.5">
                            <svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M3 11l9-7 9 7M5 10v9a1 1 0 001 1h12a1 1 0 001-1v-9"/>
                            </svg>
                            <h3 class="text-lg font-semibold text-primary">Farm Ownership</h3>
                        </div>
                        <p class="mb-4 text-sm text-muted-foreground">Are you a land owner or a tenant?</p>

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 p-4 transition"
                                   :class="f.ownership_type === 'land_owner' ? 'border-primary bg-accent/50' : 'border-border hover:border-input'">
                                <input type="radio" name="ownership_type" value="land_owner" x-model="f.ownership_type"
                                       class="mt-0.5 h-4 w-4 border-input text-primary focus:ring-ring">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-foreground">Land Owner</span>
                                    <span class="mt-1 block text-xs leading-snug text-muted-foreground">
                                        I own the land where I farm.
                                    </span>
                                </span>
                            </label>

                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border-2 p-4 transition"
                                   :class="f.ownership_type === 'tenant' ? 'border-primary bg-accent/50' : 'border-border hover:border-input'">
                                <input type="radio" name="ownership_type" value="tenant" x-model="f.ownership_type"
                                       class="mt-0.5 h-4 w-4 border-input text-primary focus:ring-ring">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-foreground">Tenant</span>
                                    <span class="mt-1 block text-xs leading-snug text-muted-foreground">
                                        I rent or lease the land where I farm.
                                    </span>
                                </span>
                            </label>
                        </div>

                        {{-- Land owner: proof of ownership --}}
                        <div x-show="f.ownership_type === 'land_owner'" x-cloak class="mt-5 sm:max-w-sm">
                            <label class="{{ $labelClass }}">
                                Barangay Certificate <span class="text-red-500">*</span>
                            </label>
                            <label class="flex h-28 cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-input bg-secondary px-4 text-center transition hover:border-primary hover:bg-accent/40">
                                <span class="text-xs text-muted-foreground"
                                      x-text="documentName || 'Upload Barangay Certificate'"></span>
                                <span class="mt-1 text-2xl leading-none text-muted-foreground">+</span>
                                <input type="file" name="barangay_certificate" class="hidden"
                                       accept=".jpg,.jpeg,.png,.pdf"
                                       @change="documentName = $event.target.files.length ? $event.target.files[0].name : ''">
                            </label>
                            <p class="mt-1.5 text-xs text-muted-foreground">
                                A certificate from your barangay confirming that you own the land you farm.
                                JPG, PNG or PDF, max 5MB.
                            </p>
                        </div>

                        {{-- Tenant: land owner details --}}
                        <div x-show="f.ownership_type === 'tenant'" x-cloak
                             class="mt-5 grid gap-x-8 gap-y-5 rounded-xl bg-muted p-5 sm:grid-cols-2">
                            <div>
                                <label class="{{ $labelClass }}">Land Owner Full Name <span class="text-red-500">*</span></label>
                                <input type="text" name="landowner_name" x-model="f.landowner_name"
                                       placeholder="Enter the land owner's name" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Land Owner Contact Number</label>
                                <input type="text" name="landowner_contact" x-model="f.landowner_contact"
                                       placeholder="09XXXXXXXXX" class="{{ $inputClass }}">
                            </div>
                            <div class="sm:col-span-2 xl:col-span-3">
                                <label class="{{ $labelClass }}">Land Owner Location <span class="text-red-500">*</span></label>
                                <input type="text" name="landowner_location" x-model="f.landowner_location"
                                       placeholder="Barangay, Municipality" class="{{ $inputClass }}">
                            </div>
                        </div>
                    </div>

                    {{-- Farm information --}}
                    <div>
                        <div class="mb-1 flex items-center gap-2.5">
                            <svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M3 17h4l1-3h6l1 3h6M6 17v3M18 17v3M7 14V8h5l3 3v3"/>
                                <circle cx="9" cy="20" r="1.5"/>
                            </svg>
                            <h3 class="text-lg font-semibold text-primary">
                                Farm Information <span class="font-normal text-muted-foreground">(optional)</span>
                            </h3>
                        </div>
                        <p class="mb-4 text-sm text-muted-foreground">You can still update these details later.</p>

                        <div class="mb-6 sm:max-w-xs">
                            <label class="{{ $labelClass }}">Farm size (ha)</label>
                            <input type="number" step="0.01" min="0" name="farm_size_hectares"
                                   x-model="f.farm_size_hectares"
                                   placeholder="Enter farm size" class="{{ $inputClass }}">
                        </div>

                        <label class="{{ $labelClass }}">Main crop type <span class="text-red-500">*</span></label>

                        <template x-for="(crop, index) in crops" :key="index">
                            <div class="mb-3 grid items-end gap-3 sm:grid-cols-[1fr_1fr_auto]">
                                <select :name="`crops[${index}][crop_id]`" x-model="crop.crop_id" required
                                        class="{{ $inputClass }}">
                                    <option value="">Select crop type</option>
                                    @foreach ($crops as $cropOption)
                                        <option value="{{ $cropOption->id }}">{{ $cropOption->name }}</option>
                                    @endforeach
                                </select>

                                <input type="text" :name="`crops[${index}][crop_specify]`" x-model="crop.crop_specify"
                                       x-show="isHvcc(crop.crop_id)" x-cloak
                                       placeholder="Specify crop (e.g. Ampalaya)" class="{{ $inputClass }}">

                                <button type="button" x-show="crops.length > 1" @click="removeCrop(index)"
                                        class="rounded-lg border border-red-200 px-3.5 py-2.5 text-xs font-medium text-red-600 hover:bg-red-50">
                                    Remove
                                </button>
                            </div>
                        </template>

                        <button type="button" @click="addCrop()"
                                class="mt-1 rounded-lg border border-primary px-4 py-2 text-sm font-medium text-primary hover:bg-accent">
                            + Add Another Crop
                        </button>
                    </div>
                </div>

                {{-- ============================================================
                     STEP 4 - REVIEW
                ============================================================= --}}
                <div x-show="step === 4" x-cloak x-ref="step4">
                    <div class="mb-5 flex items-center gap-2.5">
                        <svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" stroke-width="1.7"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9 11l3 3 7-7M20 12v7a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1h9"/>
                        </svg>
                        <h3 class="text-lg font-semibold text-primary">Review your details</h3>
                    </div>

                    <p class="mb-6 text-sm text-muted-foreground">
                        Please make sure everything below is correct before you submit. You can go back to any
                        step to make changes.
                    </p>

                    <div class="space-y-4">
                        {{-- Personal --}}
                        <div class="rounded-xl border border-border bg-muted/60 p-5">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Personal Information</h4>
                                <button type="button" @click="goTo(1)" class="text-xs font-medium text-primary hover:underline">Edit</button>
                            </div>
                            <dl class="grid gap-x-8 gap-y-3 sm:grid-cols-2">
                                <div><dt class="text-xs text-muted-foreground">Full Name</dt>
                                     <dd class="text-sm font-medium text-foreground" x-text="fullName()"></dd></div>
                                <div><dt class="text-xs text-muted-foreground">Date of Birth</dt>
                                     <dd class="text-sm font-medium text-foreground" x-text="f.date_of_birth || '-'"></dd></div>
                                <div><dt class="text-xs text-muted-foreground">Sex</dt>
                                     <dd class="text-sm font-medium capitalize text-foreground" x-text="f.sex || '-'"></dd></div>
                                <div><dt class="text-xs text-muted-foreground">Phone Number</dt>
                                     <dd class="text-sm font-medium text-foreground" x-text="f.phone_number || '-'"></dd></div>
                            </dl>
                        </div>

                        {{-- Account --}}
                        <div class="rounded-xl border border-border bg-muted/60 p-5">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Account Information</h4>
                                <button type="button" @click="goTo(2)" class="text-xs font-medium text-primary hover:underline">Edit</button>
                            </div>
                            <dl class="grid gap-x-8 gap-y-3 sm:grid-cols-2">
                                <div><dt class="text-xs text-muted-foreground">Username</dt>
                                     <dd class="text-sm font-medium text-foreground" x-text="f.username || '-'"></dd></div>
                                <div><dt class="text-xs text-muted-foreground">Email Address</dt>
                                     <dd class="text-sm font-medium break-all text-foreground" x-text="f.email || '-'"></dd></div>
                                <div><dt class="text-xs text-muted-foreground">Password</dt>
                                     <dd class="text-sm font-medium text-foreground" x-text="f.password ? '••••••••' : '-'"></dd></div>
                            </dl>
                        </div>

                        {{-- Location and farm --}}
                        <div class="rounded-xl border border-border bg-muted/60 p-5">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Location &amp; Farm</h4>
                                <button type="button" @click="goTo(3)" class="text-xs font-medium text-primary hover:underline">Edit</button>
                            </div>
                            <dl class="grid gap-x-8 gap-y-3 sm:grid-cols-2">
                                <div class="sm:col-span-2 xl:col-span-3"><dt class="text-xs text-muted-foreground">Complete Address</dt>
                                     <dd class="text-sm font-medium text-foreground" x-text="f.address || '-'"></dd></div>
                                <div><dt class="text-xs text-muted-foreground">Barangay</dt>
                                     <dd class="text-sm font-medium text-foreground" x-text="label('barangays', f.barangay_id)"></dd></div>
                                <div><dt class="text-xs text-muted-foreground">Farmers' Association</dt>
                                     <dd class="text-sm font-medium text-foreground" x-text="label('associations', f.association_id)"></dd></div>
                                <div><dt class="text-xs text-muted-foreground">Farm Ownership</dt>
                                     <dd class="text-sm font-medium text-foreground"
                                         x-text="f.ownership_type === 'tenant' ? 'Tenant' : 'Land Owner'"></dd></div>
                                <div><dt class="text-xs text-muted-foreground">Farm Size</dt>
                                     <dd class="text-sm font-medium text-foreground"
                                         x-text="f.farm_size_hectares ? f.farm_size_hectares + ' ha' : 'Not provided'"></dd></div>

                                <template x-if="f.ownership_type === 'land_owner'">
                                    <div><dt class="text-xs text-muted-foreground">Barangay Certificate</dt>
                                         <dd class="text-sm font-medium text-foreground"
                                             x-text="documentName || 'No file selected'"></dd></div>
                                </template>

                                <template x-if="f.ownership_type === 'tenant'">
                                    <div><dt class="text-xs text-muted-foreground">Land Owner</dt>
                                         <dd class="text-sm font-medium text-foreground"
                                             x-text="(f.landowner_name || '-') + (f.landowner_location ? ' of ' + f.landowner_location : '')"></dd></div>
                                </template>

                                <div class="sm:col-span-2 xl:col-span-3"><dt class="text-xs text-muted-foreground">Main Crops</dt>
                                     <dd class="text-sm font-medium text-foreground" x-text="cropSummary()"></dd></div>
                            </dl>
                        </div>
                    </div>

                    {{-- Terms --}}
                    <label class="mt-6 flex items-start gap-3 text-sm text-foreground">
                        <input type="checkbox" x-model="agreed"
                               class="mt-0.5 h-5 w-5 rounded border-2 border-input text-primary focus:ring-ring">
                        <span>
                            I agree to the
                            <a href="#" class="font-medium text-[#15803d] hover:underline">Terms of Service</a>
                            and
                            <a href="#" class="font-medium text-[#15803d] hover:underline">Privacy Policy</a>,
                            and I confirm that all the information I provided is correct and complete.
                        </span>
                    </label>

                    <p class="mt-4 text-xs text-muted-foreground">
                        Your account will be reviewed by the Municipal Agriculture Office before activation.
                    </p>
                </div>

                {{-- ===================== NAVIGATION ===================== --}}
                <div class="mt-9 flex flex-col-reverse gap-3 border-t border-border pt-6 sm:flex-row sm:items-center">

                    <a href="{{ route('login') }}" x-show="step === 1"
                       class="rounded-lg border border-input px-6 py-3 text-center text-sm font-medium text-foreground hover:bg-muted/60">
                        Cancel
                    </a>

                    <button type="button" x-show="step > 1" x-cloak @click="back()"
                            class="rounded-lg border border-input px-6 py-3 text-sm font-medium text-foreground hover:bg-muted/60">
                        Back
                    </button>

                    <button type="button" x-show="step < 4" @click="next()"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-[#15803d] px-6 py-3 text-sm font-semibold text-white transition hover:brightness-110">
                        Continue
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M5 12h14M13 6l6 6-6 6"/>
                        </svg>
                    </button>

                    <button type="submit" x-show="step === 4" x-cloak
                            class="flex flex-1 items-center justify-center gap-2.5 rounded-lg bg-[#15803d] px-6 py-3 text-sm font-semibold text-white transition hover:brightness-110">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M15 19v-1.5a4 4 0 00-4-4H6a4 4 0 00-4 4V19M8.5 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM19 8v6M22 11h-6"/>
                        </svg>
                        Create Account
                    </button>
                </div>
            </form>

            <p class="mt-8 text-center text-sm text-muted-foreground">
                Already have an account?
                <a href="{{ route('login') }}" class="font-bold text-[#15803d] hover:underline">Login here</a>
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function farmerRegistration() {
        return {
            step: {{ $startStep }},
            stepError: '',
            agreed: false,
            documentName: '',

            // Lookup tables rendered from the database, used by the review step
            lookup: {
                barangays:    @json($barangays->pluck('name', 'id')),
                associations: @json($associations->pluck('name', 'id')),
                crops:        @json($crops->pluck('name', 'id')),
                hvcc:         @json($crops->where('is_hvcc', true)->pluck('id')->values()),
            },

            f: {
                first_name:            @json(old('first_name', '')),
                middle_name:           @json(old('middle_name', '')),
                last_name:             @json(old('last_name', '')),
                date_of_birth:         @json(old('date_of_birth', '')),
                sex:                   @json(old('sex', '')),
                phone_number:          @json(old('phone_number', '')),
                username:              @json(old('username', '')),
                email:                 @json(old('email', '')),
                password:              '',
                password_confirmation: '',
                address:               @json(old('address', '')),
                barangay_id:           @json((string) old('barangay_id', '')),
                association_id:        @json((string) old('association_id', '')),
                ownership_type:        @json(old('ownership_type', 'land_owner')),
                landowner_name:        @json(old('landowner_name', '')),
                landowner_contact:     @json(old('landowner_contact', '')),
                landowner_location:    @json(old('landowner_location', '')),
                farm_size_hectares:    @json((string) old('farm_size_hectares', '')),
            },

            crops: @json($oldCrops),

            /* ---------- crop repeater ---------- */
            isHvcc(cropId) {
                return this.lookup.hvcc.map(String).includes(String(cropId));
            },
            addCrop() {
                this.crops.push({ crop_id: '', crop_specify: '' });
            },
            removeCrop(index) {
                this.crops.splice(index, 1);
            },

            /* ---------- review helpers ---------- */
            label(list, id) {
                return this.lookup[list][id] || '-';
            },
            fullName() {
                const parts = [this.f.first_name, this.f.middle_name, this.f.last_name].filter(Boolean);
                return parts.length ? parts.join(' ') : '-';
            },
            cropSummary() {
                const named = this.crops
                    .filter(crop => crop.crop_id)
                    .map(crop => {
                        const name = this.lookup.crops[crop.crop_id] || 'Crop';
                        return crop.crop_specify ? `${name} (${crop.crop_specify})` : name;
                    });

                return named.length ? named.join(', ') : '-';
            },

            /* ---------- step navigation ---------- */
            validateStep() {
                this.stepError = '';

                const container = this.$refs['step' + this.step];
                if (container) {
                    const fields = container.querySelectorAll('input, select, textarea');

                    for (const field of fields) {
                        if (field.disabled || field.offsetParent === null) continue;

                        if (!field.checkValidity()) {
                            field.reportValidity();
                            this.stepError = 'Please complete the highlighted field before continuing.';
                            return false;
                        }
                    }
                }

                if (this.step === 2 && this.f.password !== this.f.password_confirmation) {
                    this.stepError = 'The password and its confirmation do not match.';
                    return false;
                }

                if (this.step === 3 && !this.crops.some(crop => crop.crop_id)) {
                    this.stepError = 'Please select at least one main crop.';
                    return false;
                }

                return true;
            },

            next() {
                if (!this.validateStep()) return;
                if (this.step < 4) {
                    this.step++;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },

            back() {
                if (this.step > 1) {
                    this.stepError = '';
                    this.step--;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },

            goTo(target) {
                if (target < this.step) {
                    this.stepError = '';
                    this.step = target;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },

            onSubmit(event) {
                // Enter key on an earlier step should advance, never submit
                if (this.step < 4) {
                    event.preventDefault();
                    this.next();
                    return;
                }

                if (!this.agreed) {
                    event.preventDefault();
                    this.stepError = 'Please confirm that your information is correct before submitting.';
                }
            },
        };
    }
</script>
@endpush
