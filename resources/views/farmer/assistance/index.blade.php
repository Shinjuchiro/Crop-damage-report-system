@extends('layouts.app')

@section('title', 'Assistance')
@section('heading', 'Assistance Received')
@section('heading-fil', 'Natanggap na Tulong')
@section('subheading', 'Tulong na itinalaga at ibinigay ng Municipal Agriculture Office para sa inyo.')

@section('content')

{{--
    Proposal section 65, plus the receipt confirmation that closes the chain.

    The MAO selects the farmers in a distribution batch and hands the
    assistance over itself (Oct 2026). This page lists the batches the farmer
    was SELECTED for, then the hand-overs recorded against them.

    The confirmation is the part that matters. The association saying "we gave
    this out" and the farmer saying "I received it" are two separate claims,
    stored in two separate columns. If they disagree, the office needs to see
    the disagreement rather than have one quietly overwrite the other. Saying
    "I did not receive this" is a normal, useful answer, not a complaint form.
--}}

<div class="space-y-4">

    @if ($errors->any())
        <x-ui.alert variant="destructive">{{ $errors->first() }}</x-ui.alert>
    @endif

    @if ($awaiting > 0)
        <x-ui.alert variant="warning" title="Please confirm what you received">
            There {{ $awaiting === 1 ? 'is' : 'are' }} {{ $awaiting }}
            {{ $awaiting === 1 ? 'item' : 'items' }} below waiting for your answer. Tap
            "View Details" on an item, then answer "I Have Received My Assistance" or "No, I did not receive it".
            <span class="mt-1 block">
                Pakisagot po kung natanggap ninyo ang tulong. Mahalaga ito upang malaman ng opisina.
            </span>
        </x-ui.alert>
    @else
        <x-ui.alert variant="info">
            The Municipal Agriculture Office selects the farmers who receive assistance and gives it out
            itself. What you see here is the assistance you were selected for, and what was recorded as
            given to you.
            <span class="mt-1 block">
                Kung may hindi tugma, makipag-ugnayan po sa Municipal Agriculture Office.
            </span>
        </x-ui.alert>
    @endif

    {{-- ---------- SELECTED, NOT HANDED OVER YET ---------- --}}
    @foreach ($selected as $selection)
        @php $batch = $selection->allocation; @endphp
        <x-ui.card>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-1.5">
                    <p class="text-base font-semibold">{{ $batch?->display_name ?? 'Assistance' }}</p>

                    <p class="text-sm text-muted-foreground">
                        @if ($batch?->distribution_date)
                            Distribution on {{ $batch->distribution_date->format('F d, Y') }}
                            @if ($batch->venue) at {{ $batch->venue }} @endif
                        @else
                            The Municipal Agriculture Office will announce when and where to collect it.
                        @endif
                    </p>

                    <p class="text-xs text-muted-foreground">
                        AA-{{ str_pad((string) $selection->assistance_allocation_id, 3, '0', STR_PAD_LEFT) }}
                        @if ($selection->payroll_ref) &middot; Payroll No. {{ $selection->payroll_ref }} @endif
                        &middot; selected {{ $selection->created_at?->format('F d, Y') ?? '' }}
                        @if ($selection->damageReport)
                            &middot; for report {{ $selection->damageReport->reference }}
                        @endif
                    </p>
                </div>

                <div class="flex shrink-0 flex-wrap gap-2">
                    <x-ui.badge variant="info" dot>Selected for assistance</x-ui.badge>
                </div>
            </div>

            {{-- The details and the farmer's answer live in a pop-up: the card
                 only says there is something to look at. --}}
            {{-- x-data is required: $dispatch only works inside an Alpine scope. --}}
            <div x-data class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                <p class="text-sm text-muted-foreground">
                    Did you receive this? Open the details to answer.
                    <span class="block text-xs">Buksan po ang detalye para sagutin.</span>
                </p>
                <x-ui.button size="lg" @click="$dispatch('open-dialog', 'selection-{{ $selection->id }}')">
                    View Details
                </x-ui.button>
            </div>
        </x-ui.card>
    @endforeach

    @forelse ($distributions as $distribution)
        @php
            /* Section 11.4: the confirmation is only offered once the
               hand-over itself is on the record. The association writes
               'distributed' when it records giving something out, so a row
               still sitting at 'pending_distribution' is one nobody has
               handed over yet, and being asked to confirm receiving it would
               be asking the farmer to sign for nothing.

               The controller checks the same thing again on submit. This is
               the half that stops the question being asked; that is the half
               that stops an answer being accepted. */
            $handedOver = in_array($distribution->distribution_status, ['distributed', 'completed'], true);
            $waiting    = $handedOver && $distribution->receipt_status === 'pending_confirmation';
        @endphp

        <x-ui.card :class="$waiting ? 'border-primary/40' : ''">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-1.5">
                    <p class="text-base font-semibold">
                        {{ $distribution->allocation?->display_name ?? 'Assistance' }}
                    </p>

                    {{-- The amount the farmer received used to lead this
                         card, as a peso figure or a quantity. Both are gone
                         (Oct 2026, client decision): the system does not
                         record how much anybody was given. What is left is
                         the date, which is what a farmer checking this page
                         is actually looking for. --}}
                    <p class="text-sm text-muted-foreground">
                        @if ($handedOver && $distribution->distributed_at)
                            Handed over {{ $distribution->distributed_at->format('F d, Y') }}
                        @else
                            Not handed over yet
                        @endif
                    </p>

                    <p class="text-xs text-muted-foreground">
                        {{ $handedOver ? ($distribution->distributed_at?->format('F d, Y') ?? 'Date not recorded') : 'Answered ' . ($distribution->receipt_confirmed_at?->format('F d, Y') ?? '') }}
                        &middot; from the Municipal Agriculture Office
                        @if (! empty($payrollRefs[$distribution->assistance_allocation_id]))
                            &middot; Payroll No. {{ $payrollRefs[$distribution->assistance_allocation_id] }}
                        @endif
                        @if ($distribution->damageReport)
                            &middot; for report {{ $distribution->damageReport->reference }}
                        @endif
                    </p>

                    @if ($distribution->remarks)
                        <p class="text-sm leading-relaxed">{{ $distribution->remarks }}</p>
                    @endif
                </div>

                <div class="flex shrink-0 flex-wrap gap-2">
                    @if ($distribution->receipt_status === 'confirmed_received')
                        {{-- The revision's own word for this state (section
                             11.1). "You confirmed this" described the act;
                             Received is the status the office reads on its
                             own screens, so the farmer sees the same word. --}}
                        <x-ui.status :value="\App\Support\AssistanceCase::RECEIVED" />
                    @elseif ($distribution->receipt_status === 'not_received')
                        <x-ui.badge variant="danger" dot>You said not received</x-ui.badge>
                    @elseif (! $handedOver)
                        <x-ui.badge variant="neutral" dot>Not handed over yet</x-ui.badge>
                    @else
                        <x-ui.badge variant="warning" dot>Waiting for your answer</x-ui.badge>
                    @endif
                </div>
            </div>

            {{-- ---------- The question, only while it is unanswered ---------- --}}
            @if ($waiting)
                <div x-data="{ saying: null }" class="mt-4 border-t border-border pt-4">
                    <p class="text-sm font-semibold">Did you receive this?</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">Natanggap po ba ninyo ito?</p>

                    <div class="mt-3 flex flex-col gap-3 sm:flex-row">

                        {{-- YES --}}
                        <form method="POST"
                              action="{{ route('farmer.assistance.confirm', $distribution) }}"
                              class="flex-1"
                              data-confirm="Please confirm that you have received this assistance. It is recorded with your name and the date, and the Municipal Agriculture Office can see it."
                              data-confirm-title="Confirm you received this?"
                              data-confirm-action="Yes, I received it">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="receipt_status" value="confirmed_received">

                            <x-ui.button size="lg" type="submit" class="w-full">
                                <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                                     stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4.5 12.5l5 5 10-11"/></svg>
                                I Have Received My Assistance
                            </x-ui.button>
                        </form>

                        {{-- NO. Opens a reason box rather than submitting
                             straight away, because "I did not get it" with no
                             explanation gives the office nothing to act on. --}}
                        <x-ui.button size="lg" variant="outline" class="flex-1"
                                     x-show="saying !== 'no'" @click="saying = 'no'">
                            No, I did not receive it
                        </x-ui.button>
                    </div>

                    <form method="POST" x-show="saying === 'no'" x-cloak
                          action="{{ route('farmer.assistance.confirm', $distribution) }}"
                          class="mt-3"
                          data-confirm="The Municipal Agriculture Office will be able to see this. Please make sure what you have written is correct."
                          data-confirm-title="Report that you did not receive this?"
                          data-confirm-action="Confirm"
                          data-confirm-tone="danger">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="receipt_status" value="not_received">

                        <label for="note-{{ $distribution->id }}" class="block text-sm font-medium">
                            What happened? <span class="text-destructive">*</span>
                            <span class="block text-xs font-normal text-muted-foreground">
                                Pakisabi po kung ano ang nangyari
                            </span>
                        </label>

                        <textarea id="note-{{ $distribution->id }}" name="receipt_note" rows="3" required
                                  maxlength="1000"
                                  placeholder="e.g. Wala pong dumating sa amin. Hindi po ako naabisuhan."
                                  class="mt-1.5 w-full rounded-md border border-input bg-card px-3 py-2.5 text-base shadow-sm"></textarea>

                        <div class="mt-3 flex flex-col-reverse gap-2 sm:flex-row">
                            <x-ui.button size="lg" variant="ghost" type="button" @click="saying = null">
                                Cancel
                            </x-ui.button>
                            <x-ui.button size="lg" variant="destructive" type="submit">
                                Submit this answer
                            </x-ui.button>
                        </div>
                    </form>
                </div>

            {{-- ---------- Their answer, once given ---------- --}}
            @elseif ($distribution->receipt_note)
                <div class="mt-4 rounded-lg bg-muted px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        What you told the office
                    </p>
                    <p class="mt-1 whitespace-pre-line text-sm">{{ $distribution->receipt_note }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ $distribution->receipt_confirmed_at?->format('F d, Y g:i A') }}
                    </p>
                </div>
            @endif
        </x-ui.card>
    @empty
        @if ($selected->isEmpty())
        <x-ui.card :padded="false">
            <x-ui.empty title="No assistance recorded yet"
                        icon="M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3.5l4.5-2.2L12 17.5l4.5-2.2L21 17.5V21"
                        message="Assistance appears here once the Municipal Agriculture Office selects you for it.">
                <span class="text-xs text-muted-foreground">
                    Lalabas po ito kapag napili na kayo ng Municipal Agriculture Office.
                </span>
            </x-ui.empty>
        </x-ui.card>
        @endif
    @endforelse

    @if ($distributions->hasPages())
        <div class="pt-2">{{ $distributions->links() }}</div>
    @endif
</div>

{{-- One details pop-up per selection, kept outside the card wrappers so the
     fixed dialog is never positioned against a transformed parent. --}}
@foreach ($selected as $selection)
    @php $batch = $selection->allocation; @endphp
    <x-ui.dialog name="selection-{{ $selection->id }}" :title="$batch?->display_name ?? 'Assistance'"
                 description="Assistance details / Detalye ng tulong">
        <div x-data="{ saying: null }">
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Reference</dt>
                    <dd class="font-medium">AA-{{ str_pad((string) $selection->assistance_allocation_id, 3, '0', STR_PAD_LEFT) }}</dd>
                </div>
                @if ($selection->payroll_ref)
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Payroll No.</dt>
                        <dd class="font-medium">{{ $selection->payroll_ref }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Selected on</dt>
                    <dd class="font-medium">{{ $selection->created_at?->format('F d, Y') ?? '' }}</dd>
                </div>
                @if ($selection->damageReport)
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">For report</dt>
                        <dd class="font-medium">{{ $selection->damageReport->reference }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Distribution</dt>
                    <dd class="text-right font-medium">
                        @if ($batch?->distribution_date)
                            {{ $batch->distribution_date->format('F d, Y') }}@if ($batch->venue), {{ $batch->venue }}@endif
                        @else
                            To be announced by the Municipal Agriculture Office
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($batch?->details)
                <p class="mt-4 rounded-md bg-muted px-3 py-2.5 text-sm leading-relaxed">{{ $batch->details }}</p>
            @endif

            <div class="mt-5 border-t border-border pt-4">
                <p class="text-sm font-semibold">Did you receive this?</p>
                <p class="mt-0.5 text-sm text-muted-foreground">Natanggap po ba ninyo ito?</p>

                <div class="mt-3 flex flex-col gap-3 sm:flex-row">
                    <form method="POST"
                          action="{{ route('farmer.assistance.confirm-selection', $selection) }}"
                          class="flex-1"
                          data-confirm="Please confirm that you have received this assistance. It is recorded with your name and the date, and the Municipal Agriculture Office can see it."
                          data-confirm-title="Confirm you received this?"
                          data-confirm-action="Yes, I received it">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="receipt_status" value="confirmed_received">

                        <x-ui.button size="lg" type="submit" class="w-full">
                            <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                                 stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4.5 12.5l5 5 10-11"/></svg>
                            I Have Received My Assistance
                        </x-ui.button>
                    </form>

                    <x-ui.button size="lg" variant="outline" class="flex-1"
                                 x-show="saying !== 'no'" @click="saying = 'no'">
                        No, I did not receive it
                    </x-ui.button>
                </div>

                <form method="POST" x-show="saying === 'no'" x-cloak
                      action="{{ route('farmer.assistance.confirm-selection', $selection) }}"
                      class="mt-3"
                      data-confirm="The Municipal Agriculture Office will be able to see this. Please make sure what you have written is correct."
                      data-confirm-title="Report that you did not receive this?"
                      data-confirm-action="Confirm"
                      data-confirm-tone="danger">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="receipt_status" value="not_received">

                    <label for="sel-note-{{ $selection->id }}" class="block text-sm font-medium">
                        What happened? <span class="text-destructive">*</span>
                        <span class="block text-xs font-normal text-muted-foreground">Pakisabi po kung ano ang nangyari</span>
                    </label>
                    <textarea id="sel-note-{{ $selection->id }}" name="receipt_note" rows="3" required maxlength="1000"
                              placeholder="e.g. Wala pa pong ibinibigay sa akin."
                              class="mt-1.5 w-full rounded-md border border-input bg-card px-3 py-2.5 text-base shadow-sm"></textarea>

                    <div class="mt-3 flex flex-col-reverse gap-2 sm:flex-row">
                        <x-ui.button size="lg" variant="ghost" type="button" @click="saying = null">Cancel</x-ui.button>
                        <x-ui.button size="lg" variant="destructive" type="submit">Submit this answer</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </x-ui.dialog>
@endforeach
@endsection
