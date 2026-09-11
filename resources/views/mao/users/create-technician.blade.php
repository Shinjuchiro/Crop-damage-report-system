@extends('layouts.app')

@section('title', 'Add Technician')
@section('heading', 'Add Technician')
@section('subheading', 'Create a new technician account.')

@section('content')

{{-- Reference implementation of a form on the UI kit.

     Points worth copying into the Farmer and Technician modules:

     - x-ui.field owns the label, the hint and the validation message, so a
       field is never mislabelled and the error always lands under the right
       input.
     - No colour is written by hand. Everything is a token, so this page is
       correct in dark mode without a second set of classes.
     - The form carries data-confirm, so the details are shown back for
       review before anything is saved (proposal section 91).
--}}

<div>

    @if ($errors->any())
        <x-ui.alert variant="destructive" title="Please check the form" class="mb-5">
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('mao.users.technicians.store') }}"
          data-confirm="A technician account will be created with the details below. Please check them before saving."
          data-confirm-title="Create this technician account?"
          data-confirm-action="Confirm &amp; Create"
          data-confirm-review="auto">
        @csrf

        <x-ui.card title="Technician details"
                   description="They will use these credentials to sign in and receive inspection assignments.">

            <div class="space-y-5">

                <x-ui.field label="Full Name" name="full_name" required>
                    <x-ui.input name="full_name" required autocomplete="name" />
                </x-ui.field>

                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <x-ui.field label="Username" name="username" required
                                hint="What they type at the login screen.">
                        <x-ui.input name="username" required autocomplete="username" />
                    </x-ui.field>

                    <x-ui.field label="Email" name="email" required>
                        <x-ui.input name="email" type="email" required autocomplete="email" />
                    </x-ui.field>
                </div>

                <x-ui.field label="Contact Number" name="phone_number" required
                            hint="Used for urgent field verification alerts." class="sm:max-w-xs">
                    <x-ui.input name="phone_number" inputmode="tel" required />
                </x-ui.field>

                <x-ui.separator label="Password" />

                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <x-ui.field label="Temporary Password" name="password" required
                                hint="Ask them to change it after their first sign in.">
                        <x-ui.input name="password" type="password" required autocomplete="new-password" />
                    </x-ui.field>

                    <x-ui.field label="Confirm Password" name="password_confirmation" required>
                        <x-ui.input name="password_confirmation" type="password" required
                                    autocomplete="new-password" />
                    </x-ui.field>
                </div>
            </div>

            <x-slot:footer>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-ui.button variant="outline" :href="route('mao.users.index')">Cancel</x-ui.button>
                    <x-ui.button type="submit">Create Technician Account</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.card>
    </form>
</div>
@endsection
