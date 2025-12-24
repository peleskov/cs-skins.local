@extends('layouts.base', [
    'viteStyles' => 'resources/scss/cases.scss',
    'viteScripts' => 'resources/js/cases.js'
])

@section('body_class', 'cases-layout')

@section('body')
    <!-- Cases Header -->
    @include('partials.cases.header')

    <!-- Main content -->
    <main class="cases-main">
        @yield('content')
    </main>

    <!-- Cases Footer -->
    @include('partials.cases.footer')
@endsection
