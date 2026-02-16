<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('meta_description', 'CS2 Marketplace - Торговля скинами CS2')">
    <meta name="keywords" content="@yield('meta_keywords', 'cs2, skins, marketplace, торговля, скины')">
    <meta name="author" content="CS Skins">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('images/favicon_32x32.png') }}" type="image/x-icon">
    <title>@yield('title', 'CS2 Marketplace - Торговля скинами')</title>
    <meta name="theme-color" content="#ff8d2f">

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
    @vite([$viteStyles ?? 'resources/scss/mplace.scss'])

    @stack('styles')
</head>

<body class="@yield('body_class', 'position-relative')">
    @yield('body')

    <!-- Navigation data for JavaScript -->
    <script>
        window.profileTabs = @json($profileTabs ?? []);
        window.mainNavigation = @json($mainNavigation ?? []);
        window.footerData = @json($footerData ?? []);
        window.translations = @json($translations ?? []);
        @auth('client')
            window.clientId = {{ auth('client')->id() }};
        @endauth
    </script>

    <!-- Scripts -->
    <script src="{{ asset('js/vendors/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/swiper-bundle.min.js') }}"></script>

    @vite([$viteScripts ?? 'resources/js/mplace.js'])

    @stack('scripts')
</body>

</html>
