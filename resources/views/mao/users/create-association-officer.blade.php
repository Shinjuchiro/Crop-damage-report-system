@extends('layouts.app')
@section('title', 'Add Association Officer')
@section('heading', 'Add Association Officer')
@section('subheading', 'Create an account for a farmers association')

@section('content')
<div class="rounded-xl border border-border bg-card p-4 sm:p-6">

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/60 px-4 py-3 text-sm text-red-700">
            <ul class="list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Section 91: review the details in the confirmation dialog before saving. --}}
    <form method="POST" action="{{ route('mao.users.association-officers.store') }}" class="space-y-4"
          data-confirm="An association officer account will be created with the details below. Please check them before saving."
          data-confirm-title="Create this officer account?"
          data-confirm-action="Confirm &amp; Create"
          data-confirm-review="auto">
        @csrf

        <div>
            <label class="mb-1 block text-sm font-medium text-foreground">Full Name <span class="text-red-500">*</span></label>
            <input type="text" name="full_name" value="{{ old('full_name') }}" required
                   class="w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-foreground">Farmers' Association <span class="text-red-500">*</span></label>
            <select name="association_id" required
                    class="w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
                <option value="">Select association</option>
                @foreach ($associations as $association)
                    <option value="{{ $association->id }}" @selected(old('association_id') == $association->id)>
                        {{ $association->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-foreground">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" value="{{ old('username') }}" required
                       class="w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-foreground">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-foreground">Contact Number <span class="text-red-500">*</span></label>
            <input type="text" name="phone_number" value="{{ old('phone_number') }}" required
                   class="w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600 sm:max-w-xs">
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-foreground">Temporary Password <span class="text-red-500">*</span></label>
                <input type="password" name="password" required
                       class="w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-foreground">Confirm Password <span class="text-red-500">*</span></label>
                <input type="password" name="password_confirmation" required
                       class="w-full rounded-lg border border-input px-3 py-2 text-sm focus:border-green-600 focus:ring-1 focus:ring-green-600">
            </div>
        </div>

        <div class="flex gap-3 border-t border-border pt-5">
            <a href="{{ route('mao.users.index') }}"
               class="rounded-lg border border-input px-5 py-2.5 text-sm font-medium text-foreground hover:bg-muted/60">Cancel</a>
            <button type="submit"
                    class="rounded-lg bg-green-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-900">
                Create Association Officer Account
            </button>
        </div>
    </form>
</div>
@endsection
