{{--
    <x-ui.card :padded="false">
        <x-ui.table>
            <x-slot:head>
                <tr><th>Report</th><th>Farmer</th><th>Status</th></tr>
            </x-slot:head>

            @foreach ($reports as $report)
                <tr><td>...</td></tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    Plain <th> and <td> are styled by the wrapper, so rows stay readable
    without a pile of classes on every cell. The table scrolls sideways
    inside its own container rather than pushing the page wide on a phone.

    The 42rem minimum starts at sm, not at 0. A wide list table is hidden
    below sm anyway (the pages show cards instead), so the minimum only
    ever caught the small tables that ARE shown on a phone: three columns
    of a planting record, or a monthly report's summary blocks. Those were
    held at 672px inside a 364px card and had to be dragged sideways to
    read. Without the minimum they simply fit.
--}}
<div {{ $attributes->class('w-full overflow-x-auto') }}>
    <table class="w-full border-collapse sm:min-w-[42rem] text-left text-sm
                  [&_tbody_tr]:border-t [&_tbody_tr]:border-border
                  [&_tbody_tr:hover]:bg-muted/60
                  [&_td]:px-3 [&_td]:py-3 sm:[&_td]:px-4 [&_td]:align-middle [&_td]:text-foreground
                  [&_th]:px-3 [&_th]:py-3 sm:[&_th]:px-4 [&_th]:text-xs [&_th]:font-semibold
                  [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-muted-foreground">

        @isset($head)
            <thead class="bg-muted/70">
                {{ $head }}
            </thead>
        @endisset

        <tbody>
            {{ $slot }}
        </tbody>

        @isset($foot)
            <tfoot class="border-t border-border bg-muted/50">
                {{ $foot }}
            </tfoot>
        @endisset
    </table>
</div>
