@php
    /*
     |--------------------------------------------------------------------
     | Account menu (top right)
     |--------------------------------------------------------------------
     | The avatar, the name, the role and Logout used to sit side by side in
     | the top bar, which took a lot of room and put a destructive action one
     | stray click away. They are now one button that opens a small menu, the
     | way most systems do it.
     |
     | The items depend on the role, and only ever point at pages that exist.
     | A dead menu item is worse than no menu item.
     */

    $user = auth()->user();

    $roleLabels = [
        'mao'         => 'Municipal Admin',
        'technician'  => 'Technician',
        'association' => 'Association Officer',
        'farmer'      => 'Farmer',
    ];

    $items = [];

    $profileIcon  = 'M16 19v-1.5a4 4 0 00-4-4H8a4 4 0 00-4 4V19M12 9.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z';
    $settingsIcon = 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.7 1.7 0 00.3 1.9l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-2.9 1.2V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-2.9-1.2l-.1.1a2 2 0 11-2.8-2.8l.1-.1A1.7 1.7 0 003.1 14H3a2 2 0 110-4h.1a1.7 1.7 0 001.2-2.9l-.1-.1a2 2 0 112.8-2.8l.1.1A1.7 1.7 0 0010 3.1V3a2 2 0 114 0v.1a1.7 1.7 0 002.9 1.2l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 001.2 2.9H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z';

    // Every role now has a "My Profile" page (photo + basic details).
    // Farmer's is the fuller read-only registration profile that already
    // existed; Technician, Association and MAO each got a small one added
    // alongside this change, mainly so they have somewhere to put their photo.
    $profileRoutes = [
        'farmer'      => 'farmer.profile',
        'technician'  => 'technician.profile',
        'association' => 'association.profile',
        'mao'         => 'mao.profile',
    ];
    if (isset($profileRoutes[$user->role])) {
        $items[] = [
            'label' => 'My Profile',
            'url'   => route($profileRoutes[$user->role]),
            'icon'  => $profileIcon,
        ];
    }

    // Account Settings used to be a sidebar item for every role. Farmer,
    // Technician and Association's version is only the small email/phone/
    // password page (ManagesAccountSettings), so it moved here instead of
    // taking a sidebar slot. MAO's "Settings" is the larger reference-data
    // hub and keeps its sidebar item too - this is just a shortcut to it.
    $settingsRoutes = [
        'farmer'      => 'farmer.settings.index',
        'technician'  => 'technician.settings.index',
        'association' => 'association.settings.index',
        'mao'         => 'mao.settings',
    ];
    if (isset($settingsRoutes[$user->role])) {
        $items[] = [
            'label' => 'Account Settings',
            'url'   => route($settingsRoutes[$user->role]),
            'icon'  => $settingsIcon,
        ];
    }
@endphp

<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false"
     class="relative">

    {{-- Trigger --}}
    <button type="button" @click="open = ! open" :aria-expanded="open" aria-haspopup="true"
            class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 transition-colors hover:bg-accent
                   hover:text-accent-foreground">

        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary
                     text-sm font-semibold text-primary-foreground">
            {{ strtoupper(substr($user->display_name ?: $user->username, 0, 2)) }}
        </span>

        {{-- The name is hidden on a phone, where the avatar alone is enough --}}
        <span class="hidden min-w-0 text-left leading-tight sm:block">
            <span class="block truncate text-sm font-semibold">{{ $user->display_name }}</span>
            <span class="block truncate text-xs font-medium text-primary">
                {{ $roleLabels[$user->role] ?? 'User' }}
            </span>
        </span>

        <svg class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
             :class="open && 'rotate-180'"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
             stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M6 9l6 6 6-6"/>
        </svg>
    </button>

    {{-- Menu --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         class="absolute right-0 z-50 mt-2 w-60 origin-top-right overflow-hidden rounded-xl border
                border-border bg-popover text-popover-foreground shadow-lg">

        {{-- Who is signed in. On a phone the trigger does not show the name,
             so this is where you check you are in the right account. --}}
        <div class="border-b border-border px-4 py-3">
            <p class="truncate text-sm font-semibold">{{ $user->display_name }}</p>
            <p class="truncate text-xs text-muted-foreground">{{ $user->email }}</p>
        </div>

        <div class="p-1.5">
            @foreach ($items as $item)
                <a href="{{ $item['url'] }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium
                          transition-colors hover:bg-accent hover:text-accent-foreground">
                    <svg class="h-4.5 w-4.5 shrink-0 text-muted-foreground" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                         viewBox="0 0 24 24" aria-hidden="true">
                        <path d="{{ $item['icon'] }}"/>
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>

        {{-- Log out, set apart at the bottom so it is never mistaken for one
             of the navigation items. Still confirms before it signs you out. --}}
        <div class="border-t border-border p-2">
            <form method="POST" action="{{ route('logout') }}"
                  data-confirm="You will be signed out and returned to the login page."
                  data-confirm-title="Log out of the system?"
                  data-confirm-action="Yes, log out">
                @csrf
                <button type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5
                               text-sm font-semibold text-primary-foreground transition hover:brightness-110">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M15 17l5-5-5-5M20 12H9M13 3H6a1 1 0 00-1 1v16a1 1 0 001 1h7"/>
                    </svg>
                    Log Out
                </button>
            </form>
        </div>
    </div>
</div>
