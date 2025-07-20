@extends('layouts.app')

@section('title', 'Оформление заказа')
@section('meta-description', 'Оформите заказ на CS2 скины на лучшей торговой площадке')

@section('content')
@include('partials.breadcrumbs', ['title' => 'Оформление заказа'])

<div id="checkout-app"></div>
@endsection