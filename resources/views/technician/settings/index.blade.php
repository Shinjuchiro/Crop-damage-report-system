@extends('layouts.app')

@section('title', 'Account Settings')
@section('heading', 'Account Settings')
@section('heading-fil', 'Mga Setting ng Account')
@section('subheading', 'Manage your login email, phone number and password.')

@section('content')
    @include('settings.account', ['user' => auth()->user(), 'inPage' => true])
@endsection
