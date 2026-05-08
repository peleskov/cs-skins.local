@extends('layouts.base', [
'viteStyles' => ['resources/scss/cases.scss', 'resources/scss/cases-mobile.scss'],
'viteScripts' => ['resources/js/cases.js', 'resources/js/cases-mobile.js']
])

@section('body_class', 'theme-cases')

@section('body')
<div class="min-vh-100 d-flex flex-column  justify-content-between">
    <!-- Cases Header -->
    @include('partials.cases.header')

    <!-- Mobile Header & Bottom Nav (≤1023px) -->
    @include('partials.mobile-nav', ['showOnline' => true])

    <!-- Main content -->
    <main class="main flex-grow-1 d-flex flex-column">
        @yield('content')
    </main>

    <!-- Cases Footer -->
    @include('partials.cases.footer')
</div>
@endsection