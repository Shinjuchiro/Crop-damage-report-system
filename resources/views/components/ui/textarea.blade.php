{{-- <x-ui.textarea name="remarks" rows="4" placeholder="Optional notes" /> --}}
@props([
    'name'  => null,
    'value' => null,
])

@php
    $invalid = $name && $errors->has($name);

    // 16px text below sm so iOS does not zoom the page when this is focused,
    // back to 14px from sm up. Same reasoning as x-ui.input. Height is left
    // to the rows attribute, so there is no touch target concern here.
    $classes = 'block w-full rounded-md border bg-card px-3 py-2.5 text-base sm:text-sm text-foreground shadow-sm '
        . 'placeholder:text-muted-foreground transition-colors '
        . 'disabled:cursor-not-allowed disabled:opacity-60 '
        . ($invalid ? 'border-destructive' : 'border-input');
@endphp

<textarea {{ $attributes->merge(['rows' => 3, 'id' => $name, 'name' => $name])->class($classes) }}
          @if ($invalid) aria-invalid="true" @endif>{{ $value ?? ($name ? old($name) : '') }}{{ $slot }}</textarea>
