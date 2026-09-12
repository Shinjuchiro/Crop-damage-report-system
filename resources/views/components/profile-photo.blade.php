{{--
    The avatar + change/remove controls used on every role's profile page.

    <x-profile-photo :user="$user"
        :update-route="route('technician.profile.photo.update')"
        :remove-route="route('technician.profile.photo.remove')" />

    Farmer's profile page passes $farmer->user, since that page's $farmer
    variable is the Farmer model, not the User model - every other role
    just passes Auth::user() straight through from its controller.

    Proposal 91.7 (preview before it saves): choosing a file opens a small
    dialog with a live preview and a Save/Cancel choice before the upload
    is actually submitted, using the same x-ui.dialog every other
    confirmation in the system uses.
--}}
@props(['user', 'updateRoute', 'removeRoute', 'size' => 'lg'])

@php
    $dims = [
        'md' => 'h-16 w-16 text-xl',
        'lg' => 'h-20 w-20 text-2xl',
    ][$size] ?? 'h-16 w-16 text-xl';

    $dialogName = 'profile-photo-' . $user->id;
    $eventName  = 'confirm-photo-' . $user->id;
@endphp

<div class="flex flex-col items-center gap-2">
    <div x-data="{
            preview: null,
            fileName: '',
            choose() { $refs.fileInput.click(); },
            onChange(e) {
                const file = e.target.files[0];
                if (! file) return;
                this.fileName = file.name;
                this.preview = URL.createObjectURL(file);
                $dispatch('open-dialog', '{{ $dialogName }}');
            },
         }"
         @{{ $eventName }}.window="$refs.form.submit()"
         class="relative inline-flex shrink-0">

        <div class="{{ $dims }} flex items-center justify-center overflow-hidden rounded-full bg-accent
                    font-bold text-accent-foreground ring-2 ring-border">
            @if ($user->profile_photo_url)
                <img src="{{ $user->profile_photo_url }}" alt="{{ $user->display_name }}"
                     class="h-full w-full object-cover">
            @else
                {{ strtoupper(substr($user->display_name ?: $user->username, 0, 2)) }}
            @endif
        </div>

        <button type="button" @click="choose()" title="Change photo" aria-label="Change photo"
                class="absolute -bottom-1 -right-1 flex h-7 w-7 items-center justify-center rounded-full
                       border-2 border-card bg-primary text-primary-foreground shadow-sm hover:brightness-110">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                 stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/>
                <circle cx="12" cy="13" r="4"/>
            </svg>
        </button>

        {{-- The actual upload form. Hidden - the dialog below only shows a
             preview and triggers this form's submit, it never re-sends the
             file itself. --}}
        <form x-ref="form" method="POST" action="{{ $updateRoute }}" enctype="multipart/form-data" class="hidden">
            @csrf
            @method('PUT')
            <input x-ref="fileInput" type="file" name="photo"
                   accept="image/png,image/jpeg,image/webp" @change="onChange">
        </form>

        <x-ui.dialog :name="$dialogName" title="Update profile photo" size="sm">
            <div class="flex flex-col items-center gap-4">
                <img :src="preview" class="h-32 w-32 rounded-full object-cover ring-2 ring-border" alt="Preview">
                <p class="text-sm text-muted-foreground" x-text="fileName"></p>
            </div>

            <x-slot:footer>
                <x-ui.button variant="outline" type="button" @click="close()">Cancel</x-ui.button>
                <x-ui.button type="button" @click="close(); $dispatch('{{ $eventName }}')">Save Photo</x-ui.button>
            </x-slot:footer>
        </x-ui.dialog>
    </div>

    @if ($user->profile_photo_path)
        <form method="POST" action="{{ $removeRoute }}"
              data-confirm="This removes the current profile photo and goes back to initials."
              data-confirm-title="Remove profile photo?"
              data-confirm-action="Remove Photo">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-xs font-medium text-muted-foreground underline hover:text-destructive">
                Remove photo
            </button>
        </form>
    @endif
</div>
