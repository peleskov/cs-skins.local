@extends('layouts.base', [
    'viteStyles' => ['resources/scss/mplace.scss', 'resources/scss/mplace-mobile.scss'],
    'viteScripts' => ['resources/js/mplace.js', 'resources/js/mplace-mobile.js']
])

@section('body')
    <!-- Skeleton loader -->
    <div class="skeleton-loader">
        @include('partials.skeleton-loader')
    </div>

    <!-- Header -->
    @include('partials.header')

    <!-- Mobile Header & Bottom Nav (≤1023px) -->
    @include('partials.mobile-nav')

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

    <!-- Chat widget for authenticated users -->
    @auth('client')
        <div id="chat-app"></div>
    @endauth
@endsection
