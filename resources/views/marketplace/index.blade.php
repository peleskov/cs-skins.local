@extends('layouts.app')

@section('title', 'Маркетплейс - CS2 Скины')

@section('content')
@include('partials.breadcrumbs', ['title' => 'Маркетплейс'])
@include('partials.categories-section')
<div 
    id="marketplace-app"
    data-listings="{{ json_encode($featuredListings) }}"
    data-total="{{ $totalListings }}"
    data-has-more="{{ $hasMorePages ? 'true' : 'false' }}"
></div>
@endsection