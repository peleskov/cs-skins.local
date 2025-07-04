@extends('layouts.app')

@section('title', 'Маркетплейс - CS2 Скины')

@section('content')
@include('partials.breadcrumbs', ['title' => 'Маркетплейс'])

<!-- Market Section -->
@include('partials.market-section')

@endsection