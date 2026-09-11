{{--
    <x-ui.input name="username" placeholder="Enter username" required />

    The value falls back to old() automatically, so a failed validation never
    empties a farmer's form. Pass :value="..." to override.
--}}
@props([
    'name'  => null,
    'value' => null,
    'size'  => 'default',
])

@php
    $invalid = $name && $errors->has($name);

    $heights = [
        'default' => 'h-10 text-sm',
        // Use size="lg" on anything a farmer or technician fills in on a phone.
        'lg'      => 'h-12 text-base',
    ];

    $classes = 'block w-full rounded-md border bg-card px-3 text-foreground shadow-sm '
        . 'placeholder:text-muted-foreground transition-colors '
        . 'file:mr-3 file:rounded file:border-0 file:bg-secondary file:px-3 file:py-1.5 '
        . 'file:text-xs file:font-medium file:text-secondary-foreground '
        . 'disabled:cursor-not-allowed disabled:opacity-60 '
        . ($heights[$size] ?? $heights['default']) . ' '
        . ($invalid ? 'border-destructive' : 'border-input');
@endphp

<input {{ $attributes->merge([
            'type'  => 'text',
            'id'    => $name,
            'name'  => $name,
            'value' => $value ?? ($name ? old($name) : null),
        ])->class($classes) }}
       @if ($invalid) aria-invalid="true" @endif>
