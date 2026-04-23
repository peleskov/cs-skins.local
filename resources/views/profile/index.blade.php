@extends('layouts.mplace')

@section('title', 'Профиль')

@section('content')
<!-- page head section starts -->
<section class="page-head-section d-none d-lg-block">
    <div class="container page-heading">
        <h2 class="h3 mb-3 text-white text-center">Профиль</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb flex-lg-nowrap justify-content-center justify-content-lg-star">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}"><i class="ri-home-line"></i>Главная</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Профиль</li>
            </ol>
        </nav>
    </div>
</section>
<!-- page head section end -->

<!-- profile section starts -->
<section class="profile-section section-b-space">
    <div class="container">
        <!-- Vue Profile Component -->
        <div id="profile-app"
             data-client="{{ $client->toJson() }}"
             data-telegram-bot-name="{{ $telegramBotName }}"
             data-profile-tabs="{{ json_encode($profileTabs) }}"
             data-deposit-settings="{{ json_encode($depositSettings) }}">
        </div>
    </div>
</section>
<!-- profile section end -->

@endsection