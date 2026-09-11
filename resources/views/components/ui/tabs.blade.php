{{--
    The filter tabs used on the monitoring and alert pages. These are links,
    not JavaScript tabs, so each filter has its own URL and can be
    bookmarked, shared and reached with the back button.

    <x-ui.tabs :items="[
        ['label' => 'All', 'url' => route('mao.notifications.index'), 'count' => 12, 'active' => ! $category],
        ['label' => 'Disaster Advisory', 'url' => ..., 'count' => 3, 'active' => $category === 'disaster_alert'],
    ]" />
--}}
@props(['items' => []])

<div {{ $attributes->class('w-full overflow-x-auto') }}>
    <nav class="inline-flex min-w-full gap-1 rounded-lg bg-muted p-1" aria-label="Filter">
        @foreach ($items as $item)
            @php $active = $item['active'] ?? false; @endphp

            <a href="{{ $item['url'] }}"
               @if ($active) aria-current="page" @endif
               class="inline-flex shrink-0 items-center gap-2 rounded-md px-3.5 py-2 text-sm font-medium transition-colors
                      {{ $active
                          ? 'bg-card text-foreground shadow-sm'
                          : 'text-muted-foreground hover:text-foreground' }}">
                {{ $item['label'] }}

                @isset($item['count'])
                    <span class="rounded-full px-1.5 py-0.5 text-xs font-semibold
                                 {{ $active ? 'bg-accent text-accent-foreground' : 'bg-card/70 text-muted-foreground' }}">
                        {{ $item['count'] }}
                    </span>
                @endisset
            </a>
        @endforeach
    </nav>
</div>
