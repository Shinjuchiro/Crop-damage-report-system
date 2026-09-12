@extends('layouts.app')

@section('title', 'Settings')
@section('heading', 'Settings')
@section('heading-fil', 'Mga Setting')
@section('subheading', 'Manage your account email, phone number and password.')

@section('content')
    @include('settings.account')
@endsection
