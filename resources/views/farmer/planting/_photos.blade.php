{{--
    PHOTOS OF THE PLANTING (Oct 2026, optional)

    Included inside the planting form (create and edit). It relies on the
    parent Alpine scope, plantingForm(), which pulls in plantingPhotos() below:

        previews          new photos waiting to be sent
        existingPhotos    photos already saved on this record (edit only)
        removedIds        saved photos the farmer has marked for removal
        photoLimit        the ceiling for one record (PlantingController::MAX_PHOTOS)

    Photos are optional on purpose. A farmer whose field was planted weeks ago,
    or whose phone has no camera, must still be able to record the planting.
    The office sees whatever is attached in the Photos panel of the record.
--}}

<div class="space-y-3 rounded-xl border border-border bg-muted/40 p-4 sm:p-5">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
            Photos <span class="font-normal normal-case tracking-normal">(optional)</span>
        </p>
        <p class="mt-0.5 text-xs text-muted-foreground">
            A photo of the planted field helps the office confirm your record. Up to
            {{ $maxPhotos }} photos, 5 MB each.
            <span class="block">Larawan ng taniman. Hanggang {{ $maxPhotos }} na larawan.</span>
        </p>
    </div>

    <p x-show="photoNotice" x-cloak class="text-xs text-destructive" x-text="photoNotice"></p>

    {{-- Photos already saved on this record (edit form only) --}}
    <div x-show="existingPhotos.length" x-cloak class="grid grid-cols-3 gap-3 sm:grid-cols-4">
        <template x-for="photo in existingPhotos" :key="photo.id">
            <div class="relative aspect-square overflow-hidden rounded-lg border border-border"
                 :class="removedIds.includes(photo.id) ? 'opacity-40' : ''">
                <img :src="photo.url" alt="Saved planting photo" class="h-full w-full object-cover">

                <button type="button" @click="toggleExisting(photo.id)"
                        class="absolute right-1 top-1 flex h-7 w-7 items-center justify-center
                               rounded-full bg-slate-900/70 text-white"
                        :aria-label="removedIds.includes(photo.id) ? 'Keep this photo' : 'Remove this photo'">
                    <svg x-show="! removedIds.includes(photo.id)" class="h-4 w-4" fill="none"
                         stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24">
                        <path d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                    <svg x-show="removedIds.includes(photo.id)" x-cloak class="h-4 w-4" fill="none"
                         stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                         stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M3 12a9 9 0 1015-6.7M3 4v5h5"/>
                    </svg>
                </button>

                <span x-show="removedIds.includes(photo.id)" x-cloak
                      class="absolute inset-x-0 bottom-0 bg-slate-900/70 px-1 py-0.5 text-center text-[10px] font-medium text-white">
                    Will be removed
                </span>
            </div>
        </template>
    </div>

    {{-- The ids of saved photos the farmer removed, sent with the form --}}
    <template x-for="id in removedIds" :key="'rm' + id">
        <input type="hidden" name="remove_photos[]" :value="id">
    </template>

    <label x-show="newLimit() > 0"
           class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl
                  border-2 border-dashed border-input bg-card px-4 py-6 text-center
                  transition-colors hover:border-primary hover:bg-accent">
        <svg class="h-8 w-8 text-muted-foreground" fill="none" stroke="currentColor"
             stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="M3 8a2 2 0 012-2h2l1.5-2h7L17 6h2a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
            <circle cx="12" cy="13" r="3.5"/>
        </svg>
        <span class="text-sm font-semibold text-foreground"
              x-text="previews.length ? 'Add more photos' : 'Take or choose photos'"></span>
        <span class="text-xs text-muted-foreground">Kumuha o pumili ng larawan</span>

        {{-- No capture attribute: it would send a phone straight to the camera
             and remove the gallery option. Without it the phone offers Camera,
             Gallery and Files together. --}}
        <input type="file" name="photos[]" multiple accept="image/*"
               x-ref="photos" @change="onPhotos($event)" class="hidden">
    </label>

    <div x-show="previews.length" x-cloak class="grid grid-cols-3 gap-3 sm:grid-cols-4">
        <template x-for="(preview, index) in previews" :key="preview.key">
            <div class="relative aspect-square overflow-hidden rounded-lg border border-border">
                <img :src="preview.url" alt="" class="h-full w-full object-cover">

                <button type="button" @click="removePhoto(index)"
                        class="absolute right-1 top-1 flex h-7 w-7 items-center justify-center
                               rounded-full bg-slate-900/70 text-white"
                        :aria-label="'Remove photo ' + (index + 1)">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2"
                         stroke-linecap="round" viewBox="0 0 24 24">
                        <path d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>
            </div>
        </template>
    </div>

    <p class="text-xs text-muted-foreground" x-show="previews.length" x-cloak>
        <span x-text="previews.length"></span> new photo(s) ready to send.
    </p>
</div>

@once
@push('scripts')
<script>
    /* The photo half of plantingForm(), spread into it so the create and edit
       forms share one copy. `this` is the form's Alpine scope.

       The chosen files are held in `files` and the file input is rebuilt from
       them through a DataTransfer, which is the only way to remove one file
       from a multiple input or to add more to what is already picked. */
    function plantingPhotos(limit, existing) {
        return {
            photoLimit: limit,
            photoNotice: '',
            files: [],
            previews: [],
            existingPhotos: existing || [],
            removedIds: [],

            keptExisting() {
                return this.existingPhotos.filter(p => ! this.removedIds.includes(p.id)).length;
            },

            /* How many NEW photos may still be added, counting the saved ones
               that are being kept. */
            newLimit() {
                return Math.max(0, this.photoLimit - this.keptExisting());
            },

            toggleExisting(id) {
                if (this.removedIds.includes(id)) {
                    // Keeping it again must not push the record over the limit.
                    if (this.keptExisting() + this.files.length >= this.photoLimit) {
                        this.photoNotice = 'A record can hold up to ' + this.photoLimit + ' photos. '
                            + 'Remove another photo first.';
                        return;
                    }

                    this.removedIds = this.removedIds.filter(x => x !== id);
                } else {
                    this.removedIds.push(id);
                }

                this.photoNotice = '';
                this.trimToLimit();
            },

            onPhotos(event) {
                const incoming = Array.from(event.target.files);
                const known = new Set(this.files.map(f => f.name + f.size + f.lastModified));

                incoming.forEach(f => {
                    if (! known.has(f.name + f.size + f.lastModified)) {
                        this.files.push(f);
                    }
                });

                this.photoNotice = '';
                this.trimToLimit();
            },

            removePhoto(index) {
                this.files.splice(index, 1);
                this.photoNotice = '';
                this.syncPhotos();
            },

            trimToLimit() {
                if (this.files.length > this.newLimit()) {
                    this.files = this.files.slice(0, this.newLimit());
                    this.photoNotice = 'Only ' + this.photoLimit + ' photos fit in one record, so the extras were left out. '
                        + 'Hanggang ' + this.photoLimit + ' na larawan lamang.';
                }

                this.syncPhotos();
            },

            /* Rebuild the input and the previews from `files`. */
            syncPhotos() {
                this.previews.forEach(p => URL.revokeObjectURL(p.url));

                try {
                    const dt = new DataTransfer();
                    this.files.forEach(f => dt.items.add(f));
                    this.$refs.photos.files = dt.files;
                } catch (e) {
                    // Older Android browsers have no DataTransfer constructor.
                    // Fall back to the browser's own selection.
                    this.photoNotice = 'Your browser cannot combine photo selections. '
                        + 'Please choose all photos at once.';
                }

                this.previews = this.files.map(f => ({
                    key: f.name + f.size + f.lastModified,
                    url: URL.createObjectURL(f),
                }));
            },
        };
    }
</script>
@endpush
@endonce
