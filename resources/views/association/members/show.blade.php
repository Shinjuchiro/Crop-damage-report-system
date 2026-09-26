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

    The body lives in _member-detail.blade.php because the members list now
    shows the same thing in its right-hand panel. This page stays so that the
    older /members/{member} links, bookmarks, and anything else already
    pointing at them keep working, and so there is still a full-width view for
    a member with a long history.
--}}

@include('association.members._member-detail', [
    'member'      => $member,
    'association' => $association,
])

@endsection
