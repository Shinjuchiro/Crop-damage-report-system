@extends('layouts.app')

@section('title', 'Notifications')
@section('heading', 'Notifications')
@section('heading-fil', 'Mga Abiso')
@section('subheading', 'Advisories and announcements from the Municipal Agriculture Office. Mga abiso mula sa MAO.')

@section('content')

{{--
    The bell's page. One shared component draws it for all four roles (see
    components/notification/list.blade.php), and the same row component
    draws the dropdown that falls out of the bell itself, so a notification
    cannot look like one thing in the panel and another here.

    Proposal section 66. These rows are written one per recipient when an
    alert is sent, so this user only ever sees what was addressed to them -
    the controller scopes every query to their own id as well.

    Nothing is ever deleted. What was announced has to survive (section 81),
    so read and unread are the only states a row has.
--}}

<x-notification.list :items="$notifications"
                     :filter="$filter"
                     :unread="$unread"
                     index-route="farmer.notifications.index"
                     show-route="farmer.notifications.show"
                     :read-all="route('farmer.notifications.read-all')"
                     empty-message="Advisories from the Municipal Agriculture Office will appear here." />
@endsection
