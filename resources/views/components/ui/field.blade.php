{{--
    A labelled form field. Wraps the label, the control, the hint and the
    validation message so every form in the system lays out the same way and
    the label is properly tied to the input for screen readers.

    <x-ui.field label="Farm size" name="farm_size" hint="In hectares" required>
        <x-ui.input name="farm_size" type="number" step="0.01" />
    </x-ui.field>

    The name is what the error message is looked up by, so it must match the
    input's name attribute.
--}}
@props([
    'label'    => null,
    'name'     => null,
    'hint'     => null,
    'required' => false,
])

@php
    $id      = $attributes->get('for') ?? $name;
    $invalid = $name && $errors->has($name);
@endphp

<div {{ $attributes->except('for')->class('space-y-1.5') }}>

    @if ($label)
        <label @if ($id) for="{{ $id }}" @endif
               class="block text-sm font-medium text-foreground">
            {{ $label }}
            @if ($required)
                <span class="text-destructive" aria-hidden="true">*</span>
                <span class="sr-only">(required)</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint && ! $invalid)
        <p class="text-xs text-muted-foreground">{{ $hint }}</p>
    @endif

    @if ($invalid)
        <p class="text-xs font-medium text-destructive">{{ $errors->first($name) }}</p>
    @endif
</div>
