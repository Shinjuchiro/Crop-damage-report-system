@props([
    'label',
    'value',
    'suffix' => null,
    'hint'   => null,
    'href'   => null,
])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge([
        'class' => 'block rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm transition '
            . ($href ? 'card-hover hover:border-[#2f9e41]' : ''),
    ]) }}
>
    <p class="text-sm font-medium text-slate-800">{{ $label }}</p>

    <p class="mt-1 flex items-baseline gap-2">
        <span class="text-3xl font-bold tracking-tight text-slate-900">{{ $value }}</span>
        @if ($suffix)
            <span class="text-xs font-medium text-slate-500">{{ $suffix }}</span>
        @endif
    </p>

    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</{{ $tag }}>
