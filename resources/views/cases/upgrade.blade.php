@extends('layouts.cases')

@section('title', 'Апгрейд - CS2 Скины')

@section('content')
@include('partials.cases.carousel-winner')
@php
$routes = [
    'cases' => route('cases.index'),
    'caseInventory' => route('case-inventory.index'),
    'upgrade' => route('upgrade.index'),
    'deposit' => route('deposit'),
    'profile' => route('profile'),
];
@endphp
<section class="upgrade-page flex-fill d-flex flex-column"
    data-vue-component="upgrade"
    data-inventory="{{ json_encode($inventoryItems) }}"
    data-user="{{ json_encode($userData) }}"
    data-settings="{{ json_encode($settings) }}"
    data-routes="{{ json_encode($routes) }}">
</section>
@endsection
