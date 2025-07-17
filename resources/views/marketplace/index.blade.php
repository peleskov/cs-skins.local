@extends('layouts.app')

@section('title', 'Маркетплейс - CS2 Скины')

@section('content')
@include('partials.breadcrumbs', ['title' => 'Маркетплейс'])
@include('partials.categories-section')
@include('partials.marketplace-section')
@endsection