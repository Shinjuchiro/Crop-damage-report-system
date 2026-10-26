{{--
    A ROW OF BREAKDOWN BLOCKS THAT FILLS ITS WIDTH (Oct 2026)

    <x-report.breakdown-grid>
        <x-report.breakdown title="By Crop" :headers="['Crop', 'Reports']" :rows="$rows" />
        <x-report.breakdown title="By Barangay" ... />   (long: takes a row of its own)
    </x-report.breakdown-grid>

    The report pages used to put their breakdown tables two to a row. A
    three-row table beside a forty-row table leaves a tall empty block under
    the short one, which is what the office saw on the Farm Summary.

    Here the blocks wrap like words in a paragraph: each is at least 19rem
    wide and then grows to share what is left of the row, so two short tables
    fill the row between them and five wrap three then two, none of them
    stretched down to match a tall neighbour. A long list (see
    x-report.breakdown) asks for the whole row and flows into columns of its
    own, so it is a fraction of the height. Put long lists LAST in the group,
    so the short blocks above them sit together.

    The layout is inline style on purpose: it does not depend on the
    stylesheet being rebuilt for new utility classes to take effect.
--}}
<div {{ $attributes->merge(['style' => 'display:flex;flex-wrap:wrap;align-items:flex-start;gap:1.25rem']) }}>
    {{ $slot }}
</div>
