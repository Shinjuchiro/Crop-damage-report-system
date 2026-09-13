{{--
    Companion to tanza.blade.php for the rare case a page uses
    ->simplePaginate() (Previous/Next only, no page numbers) instead of
    ->paginate(). Registered as Paginator::defaultSimpleView in
    App\Providers\AppServiceProvider::boot(). Same tokens, same dark-green
    primary arrows, so a simple-paginated list still matches the rest of
    the system.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex justify-between flex-1 sm:justify-end">
        @if ($paginator->onFirstPage())
            <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-muted-foreground bg-card border border-border rounded-md cursor-default">
                {!! __('pagination.previous') !!}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-primary bg-card border border-border rounded-md hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring transition-colors">
                {!! __('pagination.previous') !!}
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-primary bg-card border border-border rounded-md hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring transition-colors">
                {!! __('pagination.next') !!}
            </a>
        @else
            <span class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-muted-foreground bg-card border border-border rounded-md cursor-default">
                {!! __('pagination.next') !!}
            </span>
        @endif
    </nav>
@endif
