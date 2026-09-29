{{--
    THE OLD STAT CARD, NOW A THIN WRAPPER OVER x-ui.stat

    <x-stat-card label="Total Farmers" :value="$data['farmer']['total_farmers']" />

    This component predates x-ui.stat and was still drawing its own card:
    hard-coded slate and white instead of the theme's tokens, px-5 py-4 of
    padding and a 30px number at every width. It is used 59 times, all on
    the two monthly report pages (mao/reports/show and
    association/summaries/show), which is why those two looked like a
    different system on a phone: big pale cards in a grid, while every other
    figure in the app had become the compact card.

    Rather than edit 59 call sites, it now hands its props straight to
    x-ui.stat. The report pages get the same card as everything else, at the
    same size, honouring the theme, and they follow it from here on.

    tone and icon are accepted so one of these can be coloured later without
    another rewrite.
--}}
@props([
    'label'  => '',
    'value'  => '0',
    'suffix' => null,
    'hint'   => null,
    'href'   => null,
    'tone'   => 'default',
    'icon'   => null,
])

<x-ui.stat :label="$label"
           :value="$value"
           :suffix="$suffix"
           :hint="$hint"
           :href="$href"
           :tone="$tone"
           :icon="$icon"
           {{ $attributes }} />
