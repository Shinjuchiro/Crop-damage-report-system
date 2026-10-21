@extends('layouts.app')

@section('title', $member->full_name)
@section('heading', $member->full_name)
@section('subheading', 'Member of ' . $association->name)

@section('header-actions')
    <x-ui.button variant="outline" :href="route('association.members.index')">Back to members</x-ui.button>
@endsection

@section('content')

{{--
    Proposal section 61: one member, everything about them in one place.

    The body lives in _member-detail.blade.php because the members list
    shows a summary of the same member in its modal. That partial renders
    the short summary there and the six tabs here, from one set of markup,
    so the two can never disagree about what a record says.
--}}

@include('association.members._member-detail', [
    'member'      => $member,
    'association' => $association,
    'tab'         => $tab,
])

@endsection
