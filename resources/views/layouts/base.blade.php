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
    @vite(is_array($viteStyles ?? null) ? $viteStyles : [$viteStyles ?? 'resources/scss/mplace.scss'])

    @stack('styles')

    {{-- Yandex Metrika --}}
    @php($yandexMetrikaId = \App\Models\SiteSetting::get('yandex_metrika'))
    @if($yandexMetrikaId)
        <script nonce="{{ app('csp-nonce') }}">
            (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r)return;}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
            (window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");
            ym({{ $yandexMetrikaId }}, "init", {clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:true});
        </script>
        <noscript><div><img src="https://mc.yandex.ru/watch/{{ $yandexMetrikaId }}" style="position:absolute;left:-9999px;" alt="" /></div></noscript>
    @endif
</head>

<body class="@yield('body_class', 'position-relative')"
    data-profile-tabs='@json($profileTabs ?? [])'
    data-main-navigation='@json($mainNavigation ?? [])'
    data-footer-data='@json($footerData ?? [])'
    data-translations='@json($translations ?? [])'
    @auth('client') data-client-id="{{ auth('client')->id() }}" @endauth
>
    @yield('body')

    <!-- UTM-трекинг партнёрской программы -->
    <script src="{{ asset('js/lr-tracking.js') }}"></script>

    <!-- Scripts -->
    <script src="{{ asset('js/vendors/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/swiper-bundle.min.js') }}"></script>

    @vite(is_array($viteScripts ?? null) ? $viteScripts : [$viteScripts ?? 'resources/js/mplace.js'])

    @stack('scripts')
</body>

</html>
