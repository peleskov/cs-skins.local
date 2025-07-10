@extends('layouts.app')

@section('title', 'Страница не найдена - CS2 Marketplace')

@section('content')
<!-- page head section starts -->
<section class="page-head-section">
    <div class="container page-heading">
        <h2 class="h3 mb-3 text-white text-center">404 Not Found</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb flex-lg-nowrap justify-content-center justify-content-lg-star">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}"><i class="ri-home-line"></i>Главная</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">404</li>
            </ol>
        </nav>
    </div>
</section>
<!-- page head section end -->

<!-- 404 section starts -->
<section class="error-section pt-0">
    <div class="container">
        <div class="banner-content text-center">
            <img class="img-fluid banner-img mx-auto" src="{{ asset('images/404.png') }}" alt="404">
            <p>
                Страница, которую вы ищете, не найдена. Ссылка на этот адрес может быть устаревшей или мы переместили её с момента последнего посещения.
            </p>
            <a href="{{ route('home') }}" class="btn theme-outline d-inline-flex mx-auto">На главную</a>
        </div>
    </div>
</section>
<!-- 404 section end -->
@endsection