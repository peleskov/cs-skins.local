@extends('layouts.cases')

@section('title', 'Кейсы - CS2 Скины')

@section('content')
@include('partials.cases.carousel-winner')
<section class="case-list flex-fill d-flex flex-column"
    data-vue-component="cases"
    data-cases="{{ json_encode($cases) }}"
    data-user="{{ Auth::guard('client')->check() ? json_encode(Auth::guard('client')->user()->only(['id', 'name', 'balance', 'steam_avatar'])) : 'null' }}">
</section>
@endsection