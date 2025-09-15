@extends('layouts.app')

@section('title', 'CS2 Marketplace - Торговля скинами')

@section('content')
{{-- Hero Section --}}
<section id="home" class="home-wrapper section-b-space overflow-hidden">
    <div class="background-effect">
        <div class="main-circle">
            <div class="main-circle circle-1">
                <div class="main-circle circle-2"></div>
            </div>
        </div>
    </div>
</section>
@include('partials.categories-section')
@auth('client')
@include('partials.case-preview')
@endauth
@include('partials.marketplace-section')
@include('partials.app-section')

@endsection