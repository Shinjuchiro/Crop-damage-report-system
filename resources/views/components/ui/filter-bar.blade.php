{{--
    Shared filter-bar wrapper, used above a list's own table/cards.

    <x-ui.filter-bar :fields="['role', 'status', 'search']">
        <x-ui.select name="role" placeholder="All roles" onchange="this.form.submit()"
                     :options="$roleLabels" :selected="request('role')" class="sm:w-48" />
        <x-ui.input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Search name or email" class="sm:min-w-[14rem] sm:flex-1" />
    </x-ui.filter-bar>

    'fields' is just the list of query parameter names this bar can set - it
    is only used to decide whether the "Clear" link should show up (i.e.
    whether any of them currently has a value). It does not render anything
    itself; each filter control is written by the page and passed in as the
    slot, since the fields differ from page to page.

    House rule (Sept 2026, filter-bar consolidation): no Search/Apply button.
    A <x-ui.select onchange="this.form.submit()"> submits itself the moment a
    choice is made, and a text <x-ui.input> submits on Enter the way a normal
    text field does. "Clear" only appears once a filter is actually active,
    and always returns to the same page with no query string at all - so it
    also clears pagination, not just the filters.

    Stacks full width on a phone, one wrapping row from sm up - same on every
    page that uses this, which is the whole point of sharing it.

    Pass :tight="true" when the surrounding layout already provides its own
    spacing below the bar (e.g. a flex container with its own gap-*) - this
    drops the bar's own bottom margin instead of letting two spacing rules
    stack. Never override the margin with a plain class="mb-0": Tailwind
    utilities of equal specificity resolve by generation order, not by where
    they sit in the HTML class list, so that kind of override is not
    reliable.
--}}
@props(['fields' => [], 'tight' => false])

<form method="GET" {{ $attributes->class(($tight ? '' : 'mb-5 ') . 'flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center') }}>
    {{ $slot }}

    @if (request()->hasAny($fields))
        <a href="{{ url()->current() }}" class="text-sm text-muted-foreground hover:text-foreground">Clear</a>
    @endif
</form>
