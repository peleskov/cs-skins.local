@extends('layouts.mplace')

@section('title', 'CS2 Marketplace - Торговля скинами')

@section('content')
{{-- Hero Section --}}
<section id="home" class="home-wrapper section-b-space overflow-hidden">
</section>
@include('partials.categories-section')
@auth('client')
@include('partials.case-preview')
@endauth
@include('partials.marketplace-section')
@include('partials.app-section')

@endsection