@extends('layouts.cases')

@section('title', 'Мой инвентарь - CS2 Скины')

@section('content')
@php
$routes = [
    'cases' => route('cases.index'),
    'caseInventory' => route('case-inventory.index'),
    'upgrade' => route('upgrade.index'),
    'deposit' => route('deposit'),
    'profile' => route('profile'),
];
@endphp
@include('partials.cases.mobile-balance')
<section class="case-inventory flex-fill d-flex flex-column"
    data-vue-component="case-inventory"
    data-items="{{ json_encode($inventoryData) }}"
    data-pagination="{{ json_encode($inventoryPagination) }}"
    data-counts="{{ json_encode($inventoryCounts) }}"
    data-user="{{ json_encode($userData) }}"
    data-favorite-case="{{ json_encode($favoriteCase) }}"
    data-best-item="{{ json_encode($bestItem) }}"
    data-routes="{{ json_encode($routes) }}">
</section>
@endsection
