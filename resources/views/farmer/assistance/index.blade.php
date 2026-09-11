@extends('layouts.app')

@section('title', 'Assistance')
@section('heading', 'Assistance Received')
@section('heading-fil', 'Natanggap na Tulong')
@section('subheading', 'Tulong na naitala ng inyong asosasyon para sa inyo.')

@section('content')

{{--
    Proposal section 65, plus the receipt confirmation that closes the chain.

    Assistance moves MAO -> Association -> Farmer, so what appears here are
    the distributions the association recorded against this farmer.

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
            {{ $awaiting === 1 ? 'item' : 'items' }} below waiting for your answer. Tap Yes if you received
            it, or No if you did not.
            <span class="mt-1 block">
                Pakisagot po kung natanggap ninyo ang tulong. Mahalaga ito upang malaman ng opisina.
            </span>
        </x-ui.alert>
    @else
        <x-ui.alert variant="info">
            The Municipal Agriculture Office allocates assistance to your Farmers' Association, and the
            association distributes it to members. What you see here is what was recorded as given to you.
            <span class="mt-1 block">
                Kung may hindi tugma, makipag-ugnayan po sa inyong asosasyon.
            </span>
        </x-ui.alert>
    @endif

    @forelse ($distributions as $distribution)
        @php
            $assistance = $distribution->allocation?->assistance;
            $waiting    = $distribution->receipt_status === 'pending_confirmation';
        @endphp

        <x-ui.card :class="$waiting ? 'border-primary/40' : ''">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-1.5">
                    <p class="text-base font-semibold">
                        {{ $assistance?->name ?? $distribution->in_kind_description ?? 'Assistance' }}
                    </p>

                    <p class="text-sm text-muted-foreground">
                        @if ($assistance?->type === 'cash')
                            &#8369;{{ number_format((float) $distribution->quantity, 2) }}
                        @else
                            {{ number_format((float) $distribution->quantity, 2) }} received
                        @endif

                        @if ($distribution->allocation?->disaster)
                            &middot; for {{ $distribution->allocation->disaster->name }}
                        @endif
                    </p>

                    <p class="text-xs text-muted-foreground">
                        {{ $distribution->distributed_at?->format('F d, Y') ?? 'Date not recorded' }}
                        @if ($distribution->allocation?->association)
                            &middot; from {{ $distribution->allocation->association->name }}
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
                        <x-ui.badge variant="success" dot>You confirmed this</x-ui.badge>
                    @elseif ($distribution->receipt_status === 'not_received')
                        <x-ui.badge variant="danger" dot>You said not received</x-ui.badge>
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
                              data-confirm="Please confirm that you received this assistance. This is recorded and the office can see it."
                              data-confirm-title="Confirm you received this?"
                              data-confirm-action="Yes, I received it">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="receipt_status" value="confirmed_received">

                            <x-ui.button size="lg" type="submit" class="w-full">
                                <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                                     stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4.5 12.5l5 5 10-11"/></svg>
                                Yes, I received it
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
                          data-confirm="The office and your association will be able to see this. Please make sure what you have written is correct."
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
        <x-ui.card :padded="false">
            <x-ui.empty title="No assistance recorded yet"
                        icon="M12 8.2c1-1.7 3.6-1.5 3.6.6 0 1.7-2.1 3.4-3.6 4.6-1.5-1.2-3.6-2.9-3.6-4.6 0-2.1 2.6-2.3 3.6-.6zM3 21v-3.5l4.5-2.2L12 17.5l4.5-2.2L21 17.5V21"
                        message="Assistance appears here once your association records giving it to you.">
                <span class="text-xs text-muted-foreground">
                    Lalabas po ito kapag naitala na ng inyong asosasyon.
                </span>
            </x-ui.empty>
        </x-ui.card>
    @endforelse

    @if ($distributions->hasPages())
        <div class="pt-2">{{ $distributions->links() }}</div>
    @endif
</div>
@endsection
