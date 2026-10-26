{{--
    A TENANT'S SUPPORTING DOCUMENTS (Oct 2026)

        <x-farmer.supporting-documents :farmer="$farmer" class="mt-4" />

    The agreement, authorization letter and similar papers a tenant uploaded
    when they registered. Draws nothing for a land owner, who has the
    Barangay Certificate instead, and a short note for a tenant who has none
    on file (applications made before this existed).

    Every file opens in a NEW TAB through AttachmentController, which asks
    whether the signed-in person may see this farmer's files before it sends
    a single byte. A new tab, not a modal, so the page the reviewer is on
    keeps its place and layout; the browser's own viewer handles images and
    PDFs. Images also show a small thumbnail so the reviewer can tell the
    papers apart without opening each one.

    Callers pass the farmer; the relation is read here (one query), so list
    screens that do not want it simply do not use this component.
--}}

@props(['farmer'])

@if ($farmer->ownership_type === 'tenant')
    @php $documents = $farmer->supportingDocuments; @endphp

    <div {{ $attributes->class(['text-sm']) }}>
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Supporting Documents</p>

        @forelse ($documents as $document)
            <a href="{{ $document->url }}" target="_blank" rel="noopener"
               class="mt-2 flex items-center gap-3 rounded-lg border border-border bg-card px-3 py-2 hover:border-primary hover:bg-accent/40">
                @if ($document->is_image)
                    <img src="{{ $document->url }}" alt="" loading="lazy"
                         class="h-10 w-10 shrink-0 rounded object-cover">
                @else
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-red-50 text-[10px] font-bold text-red-700">
                        {{ $document->is_pdf ? 'PDF' : 'FILE' }}
                    </span>
                @endif

                <span class="min-w-0 flex-1">
                    <span class="block truncate font-medium text-foreground">{{ $document->file_name }}</span>
                    @if ($document->size_label)
                        <span class="block text-xs text-muted-foreground">{{ $document->size_label }}</span>
                    @endif
                </span>

                <span class="shrink-0 text-xs font-semibold text-primary">View</span>
            </a>
        @empty
            <p class="mt-1 text-muted-foreground">No supporting documents were uploaded.</p>
        @endforelse
    </div>
@endif
