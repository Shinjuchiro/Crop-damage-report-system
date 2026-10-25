{{--
    REPORT TYPE CHOICES

        <x-report.type-picker />

    Pick one or more kinds of report to build for the chosen month, or All.
    Posts as types[] next to month and year. Choosing All ticks and locks the
    rest; ticking any single type unticks All. Nothing ticked means All.
--}}

@props(['selected' => ['all']])

<div x-data="{
        all: @js(in_array('all', $selected)),
        picked: @js(array_values(array_diff($selected, ['all']))),
        toggleAll() { if (this.all) this.picked = []; },
        toggle() { this.all = this.picked.length === 0; },
     }">
    <p class="mb-1.5 text-sm font-medium text-foreground">Report type</p>
    <p class="mb-2 text-xs text-muted-foreground">Choose one or more, or All. Each one lists every record for the month.</p>

    <div class="space-y-2">
        @foreach (\App\Services\MonthlyReportBuilder::TYPES as $key => $label)
            <label class="flex items-center gap-2.5 rounded-lg border border-border px-3 py-2 text-sm">
                <input type="checkbox" name="types[]" value="{{ $key }}" x-model="picked" @change="toggle()"
                       class="h-4 w-4 rounded border-input text-primary">
                {{ $label }}
            </label>
        @endforeach

        <label class="flex items-center gap-2.5 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm font-medium">
            <input type="checkbox" name="types[]" value="all" x-model="all" @change="toggleAll()"
                   class="h-4 w-4 rounded border-input text-primary">
            All (includes the farmer and farm summaries)
        </label>
    </div>
</div>
