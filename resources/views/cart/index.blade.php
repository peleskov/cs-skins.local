@extends('layouts.mplace')

@section('title', 'Корзина - CS2 Скины')

@section('content')
@include('partials.breadcrumbs', ['title' => 'Корзина'])

<div id="cart-app"
    data-user="{{ auth('client')->check() ? json_encode([
        'id' => auth('client')->user()->id,
        'name' => auth('client')->user()->name,
        'steam_avatar' => auth('client')->user()->steam_avatar,
        'balance' => auth('client')->user()->balance ?? 0
    ]) : 'null' }}"
    data-routes="{{ json_encode([
        'home' => route('home'),
        'marketplace' => route('marketplace.index'),
        'checkout' => route('checkout'),
        'login' => route('auth.steam')
    ]) }}">
</div>
@endsection