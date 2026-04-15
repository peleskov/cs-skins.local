@extends('layouts.base')

@section('body')
    @include('partials.header')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

    <button class="scroll scroll-to-top">
        <i class="ri-arrow-up-s-line arrow"></i>
    </button>
@endsection
