{{--
    <x-ui.select name="barangay_id" placeholder="Select barangay"
                 :options="$barangays->pluck('name', 'id')" :selected="old('barangay_id')" />

    Or write the options by hand:

    <x-ui.select name="status">
        <option value="active">Active</option>
    </x-ui.select>
--}}
@props([
    'name'        => null,
    'options'     => [],
    'selected'    => null,
    'placeholder' => null,
    'size'        => 'default',
])

@php
    $invalid = $name && $errors->has($name);
    $current = (string) ($selected ?? ($name ? old($name) : ''));

    // 16px text and a 44px box below sm, so iOS does not zoom the page on
    // focus, and the control clears the touch target minimum. Unchanged from
    // sm up. Same reasoning as x-ui.input.
    $heights = [
        'default' => 'h-11 text-base sm:h-10 sm:text-sm',
        'lg'      => 'h-12 text-base',
    ];

    $classes = 'block w-full appearance-none rounded-md border bg-card px-3 pr-9 text-foreground shadow-sm '
        . 'transition-colors disabled:cursor-not-allowed disabled:opacity-60 '
        . ($heights[$size] ?? $heights['default']) . ' '
        . ($invalid ? 'border-destructive' : 'border-input');
@endphp

<div class="relative">
    <select {{ $attributes->merge(['id' => $name, 'name' => $name])->class($classes) }}
            @if ($invalid) aria-invalid="true" @endif>

        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $value => $label)
            <option value="{{ $value }}" @selected($current === (string) $value)>{{ $label }}</option>
        @endforeach

        {{ $slot }}
    </select>

    {{-- The native arrow is hidden above so it looks the same in every browser --}}
    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
         stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M6 9l6 6 6-6"/>
    </svg>
</div>
