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
@include('partials.case-section')
<section class="popular-restaurant banner-section section-b-space ratio3_2 overflow-hidden">
    <div class="container-fluid">
        <div class="title text-center">
            <h2>Магазин скинов</h2>
            <div class="loader-line" style="left: calc(50% - 40px);"></div>
            <div class="sub-title">
                <p>Найдите популярные скины рядом.</p>
            </div>
        </div>
        <!-- Сортировка и количество -->
        <div class="row justify-content-between align-items-center mb-4">
            <div class="col-md-6">
                @if($totalListings > 0)
                    <p class="small text-muted mb-0">Всего предложений <span id="total-count">{{ $totalListings }}</span>, показано {{ $featuredListings->count() }}</p>
                @endif
            </div>
            @if($totalListings > 0)
            <div class="col-auto">
                <a href="{{ route('marketplace.index') }}" class="btn theme-btn mt-0">
                    Смотреть все
                </a>
            </div>
            @endif
        </div>

        <!-- Контейнер для товаров -->
        @if($featuredListings->count() > 0)
        <div class="row g-4" id="listings-container">
            @foreach($featuredListings as $listing)
            <div class="col-lg-2 col-md-4">
                <div class="vertical-product-box">
                    @if($listing->is_stattrak)
                    <div class="seller-badge new-badge"><img class="img-fluid badge" src="https://cs-skins.s1temaker.ru/images/svg/star-white.svg" alt="medal">
                        <h6>ST</h6>
                    </div>
                    @endif
                    <div class="vertical-product-box-img">
                        <a href="{{ route('marketplace.show', $listing->id) }}">
                            <img class="product-img-top w-100 bg-img skin-image" src="{{ $listing->item->image_url }}" alt="{{ $listing->item->name_ru }}" onerror="this.parentElement.parentElement.classList.add('image-error'); this.onerror=null;">
                        </a>
                        <div class="offers">
                            <div class="d-flex align-items-center justify-content-between">
                                <h4>${{ number_format($listing->price, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="vertical-product-body">
                        <div class="d-flex flex-column mt-sm-3 mt-2 mb-2">
                            <a href="{{ route('marketplace.show', $listing->id) }}">
                                <h4 class="vertical-product-title">{{ $listing->item->name_ru }}</h4>
                            </a>
                            <h5 class="product-items mb-2">{{ $listing->wear_name }} {{ __('items.rarities.' . $listing->item->rarity) }}</h5>
                            <p class="text-muted small">от {{ $listing->seller->name }}</p>
                        </div>
                        <div class="location-distance d-flex align-items-center justify-content-between pt-sm-3 pt-2">
                            <a href="#" class="btn theme-outline cart-btn rounded-2">В корзину</a>
                            <a href="#!" class="like-btn">
                                <i class="ri-heart-3-fill fill-icon"></i>
                                <i class="ri-heart-3-line outline-icon"></i>
                                <div class="effect-group">
                                    <span class="effect"></span>
                                    <span class="effect"></span>
                                    <span class="effect"></span>
                                    <span class="effect"></span>
                                    <span class="effect"></span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <!-- Сообщение об отсутствии товаров -->
        <div class="text-center py-5">
            <i class="ri-shopping-bag-line display-4 text-muted mb-3"></i>
            <h4 class="text-muted">Пока нет предложений</h4>
            <p class="text-muted">Станьте первым, кто выставит свои скины на продажу!</p>
        </div>
        @endif
    </div>
</section>
@include('partials.app-section')

@endsection