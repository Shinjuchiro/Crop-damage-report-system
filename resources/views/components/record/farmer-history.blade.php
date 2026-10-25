{{--
    A FARMER'S FULL HISTORY, INSIDE A RECORD'S VIEW

        <x-record.farmer-history title="All reports from this farmer" :items="$rows" />

    The grouped lists keep a farmer's latest three records; this is where
    the rest are. $items is a list of ['label', 'meta', 'href', 'status',
    'current'] arrays, newest first.
--}}

@props(['title' => 'Full history', 'items' => []])

@if (count($items) > 1)
    <div class="mt-5 border-t border-border pt-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            {{ $title }} ({{ count($items) }})
        </p>
        <ul class="mt-2 divide-y divide-border rounded-lg border border-border">
            @foreach ($items as $item)
                <li>
                    <a href="{{ $item['href'] }}"
                       class="flex items-center justify-between gap-3 px-3 py-2 text-sm hover:bg-muted/60
                              {{ ! empty($item['current']) ? 'bg-accent/50 font-semibold' : '' }}">
                        <span class="min-w-0">
                            <span class="block text-card-foreground">{{ $item['label'] }}</span>
                            <span class="block text-xs text-muted-foreground">{{ $item['meta'] }}</span>
                        </span>
                        @if (! empty($item['status']))
                            <x-ui.status :value="$item['status']" />
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
