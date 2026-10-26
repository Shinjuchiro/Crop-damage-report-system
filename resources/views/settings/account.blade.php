{{--
    ACCOUNT SETTINGS, AT THE BOTTOM OF MY PROFILE (Oct 2026)

    Included at the very end of every role's My Profile page:

        @include('settings.account', ['user' => auth()->user()])

    It used to be a page of its own (Account Settings in the account menu for
    every role, and a section of MAO's Settings hub). The office asked for the
    two to be one page, so there is no separate page any more: the old
    /settings URLs redirect to My Profile, and the forms below still post to
    the same routes, which send the person back here, to this section.

    Deliberately narrow in SCOPE: the two things every account can change
    about itself are its login email/phone and its password. Personal and
    farm information stays read-only, as it is everywhere else in the system
    (see Farmer/ProfileController for why).

    Proposal section 91.3 (Edit -> Review Changes -> Confirm -> Save): both
    forms carry data-confirm-review="auto", so pressing Update always shows
    exactly what is about to change before anything is saved.

    Stacked in one column rather than two side by side: it sits under a
    profile card, and the profile pages for Technician, Association and MAO
    are a single narrow column.
--}}

<section id="account-settings" class="scroll-mt-24 border-t border-border pt-8">
    <h2 class="text-lg font-semibold text-foreground">Account Settings</h2>
    <p class="mb-5 mt-1 text-sm text-muted-foreground">Manage your login email, phone number and password.</p>

    <div class="grid gap-6">
        <x-ui.card title="Account Details" description="Your login email and contact number. Everything else on your profile is set by the office and can't be changed here.">
            <form method="POST" action="{{ route($user->role . '.settings.update') }}"
                  data-confirm="Are you sure you want to update your account details?"
                  data-confirm-title="Review account details"
                  data-confirm-action="Update Details"
                  data-confirm-review="auto"
                  class="space-y-4">
                @csrf
                @method('PUT')

                <x-ui.field label="Email Address" name="email" required>
                    <x-ui.input type="email" name="email" :value="old('email', $user->email)" required />
                </x-ui.field>

                <x-ui.field label="Phone Number" name="phone_number" required hint="Used for account contact and, where enabled, urgent SMS alerts.">
                    <x-ui.input type="tel" name="phone_number" :value="old('phone_number', $user->phone_number)" required />
                </x-ui.field>

                <x-ui.button type="submit">Update Details</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card title="Change Password" description="Choose a password with at least 8 characters, including a letter and a number.">
            <form method="POST" action="{{ route($user->role . '.settings.password') }}"
                  data-confirm="Are you sure you want to change your password?"
                  data-confirm-title="Confirm password change"
                  data-confirm-action="Update Password"
                  data-confirm-review="auto"
                  class="space-y-4">
                @csrf
                @method('PUT')

                <x-ui.field label="Current Password" name="current_password" required>
                    <x-ui.input type="password" name="current_password" autocomplete="current-password" required />
                </x-ui.field>

                <x-ui.field label="New Password" name="password" required>
                    <x-ui.input type="password" name="password" autocomplete="new-password" required />
                </x-ui.field>

                <x-ui.field label="Confirm New Password" name="password_confirmation" required>
                    <x-ui.input type="password" name="password_confirmation" autocomplete="new-password" required />
                </x-ui.field>

                <x-ui.button type="submit">Update Password</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</section>
