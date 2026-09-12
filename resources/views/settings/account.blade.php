{{--
    Shared by farmer.settings.index, technician.settings.index and
    association.settings.index (App\Http\Controllers\Concerns\ManagesAccountSettings).
    MAO's own "Settings" is a different page entirely - the reference-data hub
    at mao.settings - and keeps its own view.

    Deliberately narrow in SCOPE: the two things every account can change
    about itself are its login email/phone and its password. Personal and
    farm information stays read-only here, the same as it is everywhere else
    in the system - see Farmer/ProfileController for why. Narrow in scope is
    not the same as narrow on screen, though (section 7: "No page-level width
    caps. Pages fill the width.") - the two cards sit side by side on a wide
    screen instead of stacking in one capped column with empty space beside
    them, and collapse to one column below lg the same way every other
    two-card layout in the system already does (e.g. mao/reports/index).

    Proposal section 91.3 (Edit -> Review Changes -> Confirm -> Save): both
    forms below carry data-confirm-review="auto", so pressing Update always
    shows exactly what is about to change before anything is saved.
--}}

<div class="space-y-6">

    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-card px-5 py-4 shadow-sm">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-accent
                     text-sm font-semibold text-accent-foreground">
            {{ strtoupper(substr($user->display_name, 0, 1)) }}
        </span>
        <div class="min-w-0">
            <p class="truncate font-semibold text-foreground">{{ $user->display_name }}</p>
            <p class="text-sm text-muted-foreground">
                Signed in as <span class="font-medium text-foreground">{{ $user->username }}</span>
                &middot; {{ ucfirst($user->role) }}
            </p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">

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
</div>
