<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CS2 Marketplace - Торговля скинами CS2">
    <meta name="keywords" content="cs2, skins, marketplace, торговля, скины">
    <meta name="author" content="CS Skins">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('images/favicon.png') }}" type="image/x-icon">
    <title>@yield('title', 'CS2 Marketplace - Торговля скинами')</title>
    <link rel="apple-touch-icon" href="{{ asset('images/favicon.png') }}">
    <meta name="theme-color" content="#ff8d2f">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="CS Skins">
    <meta name="msapplication-TileImage" content="{{ asset('images/favicon.png') }}">
    <meta name="msapplication-TileColor" content="#FFFFFF">

    <!-- Google font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Vendor CSS -->
    <link rel="stylesheet" type="text/css" href="{{ asset('css/vendors/bootstrap.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('css/vendors/swiper-bundle.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('fonts/remixicon.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('css/animate.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('css/fancybox.css') }}">

    <!-- Theme CSS -->
    @vite(['resources/scss/style.scss'])

    @stack('styles')
</head>

<body class="position-relative">
    <!-- Skeleton loader -->
    <div class="skeleton-loader">
        @include('partials.skeleton-loader')
    </div>

    <!-- Header -->
    @include('partials.header')

    <!-- Main content -->
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    @include('partials.footer')

    <!-- Tap to top -->
    <button class="scroll scroll-to-top">
        <i class="ri-arrow-up-s-line arrow"></i>
    </button>

    <!-- Scripts -->
    <!-- Библиотеки -->
    <script src="{{ asset('js/vendors/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/swiper-bundle.min.js') }}"></script>
    
    <!-- Кастомные скрипты через Vite -->
    @vite(['resources/js/app.js'])

    @stack('scripts')
</body>

</html>