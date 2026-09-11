@extends('layouts.app')
@section('title', ucfirst('farmer') . ' Dashboard')
@section('heading', 'Welcome back!')
@section('subheading', 'Crop damage reporting and assistance allocation system')

@section('content')
<div class="rounded-xl border border-slate-200 bg-white p-6">
    <h3 class="mb-2 text-lg font-semibold text-slate-800">farmer Dashboard</h3>
    <p class="text-sm text-slate-600">
        Authentication is working. This dashboard will be built in a later step.
    </p>

    <div class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
        <p><span class="font-medium">Logged in as:</span> {{ auth()->user()->username }}</p>
        <p><span class="font-medium">Role:</span> {{ auth()->user()->role }}</p>
        <p><span class="font-medium">Status:</span> {{ auth()->user()->status }}</p>
    </div>
</div>
@endsection
