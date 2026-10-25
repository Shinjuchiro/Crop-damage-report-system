{{-- My Membership Application, shown inside My Profile (revision: the sidebar
     entry is gone). Needs $farmer, $status, $history. --}}
<div id="membership" class="space-y-5">

    {{-- ---------- WHERE THE APPLICATION STANDS ---------- --}}
    <x-ui.card title="Status" description="Your membership with the Municipal Agriculture Office.">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.status :value="$status" />
            <p class="text-sm text-muted-foreground">
                {{ \App\Support\MembershipApplication::means($status) ?: 'Your membership is active.' }}
            </p>
        </div>

        <dl class="mt-5 grid gap-4 border-t border-border pt-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Application No.</dt>
                <dd class="mt-0.5 font-medium text-foreground">
                    APP-{{ str_pad($farmer->id, 3, '0', STR_PAD_LEFT) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Farmers' Association</dt>
                <dd class="mt-0.5 font-bold text-foreground">
                    {{ $farmer->association->name ?? 'Not part of an association' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Barangay</dt>
                <dd class="mt-0.5 font-medium text-foreground">{{ $farmer->barangay->name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Submitted</dt>
                <dd class="mt-0.5 font-medium text-foreground">
                    {{ $farmer->created_at?->format('F j, Y') ?? '-' }}
                </dd>
            </div>
        </dl>

        {{-- Only if it happened. A farmer whose application went straight
             through has nothing to read here, and a "Resubmitted 0 times"
             line would invite them to wonder what they got wrong. --}}
        @if ($farmer->resubmission_count > 0)
            <p class="mt-4 rounded-lg bg-muted px-3 py-2.5 text-xs text-muted-foreground">
                You corrected and resubmitted this application
                {{ $farmer->resubmission_count }}
                {{ \Illuminate\Support\Str::plural('time', $farmer->resubmission_count) }}
                before it was approved.
            </p>
        @endif
    </x-ui.card>

    {{-- ---------- THE TWO HANDOVERS ----------
         The part with no other home in the farmer's area. Each block renders
         only when there is something real to show: an application approved
         before October 2026 has no officer or officer's name recorded,
         because those columns did not exist, and a blank "Verified by -"
         would be inventing one. --}}
    @if ($farmer->forwarded_at || $farmer->decided_at)
        <x-ui.card title="How your membership was approved"
                   description="Your association verifies that you are one of its members, then the Municipal Agriculture Office decides.">

            <ol class="space-y-4">
                @if ($farmer->forwarded_at)
                    <li class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 dark:border-green-900 dark:bg-green-950/50">
                        <p class="text-sm font-semibold text-green-800 dark:text-green-300">
                            Verified by {{ $farmer->association->name ?? 'your association' }}
                        </p>
                        <p class="mt-0.5 text-xs text-green-700 dark:text-green-400">
                            {{ $farmer->forwarded_at->format('F j, Y') }}
                            @if ($farmer->forwardedBy)
                                &middot; {{ $farmer->forwardedBy->display_name }}
                            @endif
                        </p>
                        <p class="mt-1.5 text-sm text-green-900 dark:text-green-200">
                            Your association confirmed your details and sent your application on to the
                            Municipal Agriculture Office.
                        </p>
                    </li>
                @endif

                @if ($farmer->decided_at)
                    <li class="rounded-xl border border-border bg-muted px-4 py-3">
                        <p class="text-sm font-semibold text-foreground">
                            Approved by the Municipal Agriculture Office
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ $farmer->decided_at->format('F j, Y') }}
                            @if ($farmer->decidedBy)
                                &middot; {{ $farmer->decidedBy->display_name }}
                            @endif
                        </p>

                        {{-- The office's note, if they left one. Optional on an
                             approval, so most farmers will not see this. --}}
                        @if ($farmer->decision_note)
                            <p class="mt-1.5 border-t border-border pt-1.5 text-sm text-foreground">
                                {{ $farmer->decision_note }}
                            </p>
                        @endif
                    </li>
                @endif
            </ol>
        </x-ui.card>
    @endif

    {{-- ---------- HISTORY ----------
         The same audit trail the office and the association read, from
         App\Support\MembershipApplication::history(), rather than a second
         friendlier version that could drift from it.

         Remarks are included. Everything in this trail about this farmer was
         either written to them or written about their own application, so
         there is nothing here they should not see - and a farmer who was once
         asked to correct something is entitled to the note that asked. --}}
    <x-ui.card title="History" description="Every step your application went through.">
        @forelse ($history as $entry)
            <div class="mb-2 rounded-lg bg-muted px-3 py-2.5">
                <p class="text-sm font-medium text-foreground">{{ $entry->action }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ \App\Support\MembershipApplication::transitionLabel($entry->from_status) }}
                    <span aria-hidden="true">&rarr;</span><span class="sr-only">to</span>
                    <span class="font-medium text-foreground">
                        {{ \App\Support\MembershipApplication::transitionLabel($entry->to_status) }}
                    </span>
                    &middot; {{ $entry->created_at?->format('M j, Y g:i A') ?? '-' }}
                </p>
                @if ($entry->remarks)
                    <p class="mt-1.5 border-t border-border pt-1.5 text-xs text-foreground">
                        {{ $entry->remarks }}
                    </p>
                @endif
            </div>
        @empty
            <p class="rounded-lg bg-muted px-3 py-2.5 text-sm text-muted-foreground">
                No steps recorded. Applications handled before October 2026 were not tracked step by
                step, so there is nothing to show here for older memberships.
            </p>
        @endforelse
    </x-ui.card>
</div>
