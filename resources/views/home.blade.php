@extends('layouts.mplace')

@section('title', 'CS2 Marketplace - Торговля скинами')

@section('content')
{{-- Mobile-only: баннер → кейсы → поиск --}}
<div class="d-lg-none p-3">
    @include('partials.app-section')

    @auth('client')
        <div class="mobile-cases-preview mt-3">
            @include('partials.case-preview')
        </div>
    @endauth

    <div id="mobile-home-filters-app" class="mt-3" data-marketplace-url="{{ route('marketplace.index') }}"></div>
</div>

{{-- Hero Section --}}
<section id="home" class="home-wrapper section-b-space overflow-hidden">
</section>
@include('partials.categories-section')
@auth('client')
    <div class="d-none d-lg-block">
        @include('partials.case-preview')
    </div>
@endauth
@include('partials.marketplace-section')

{{-- Desktop-only: баннер снизу --}}
<div class="d-none d-lg-block">
    @include('partials.app-section')
</div>

@endsection