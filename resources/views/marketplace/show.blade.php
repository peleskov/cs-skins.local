@extends('layouts.app')

@section('title', ($listing->inventory_item_name ?: $listing->market_hash_name ?: 'Неизвестный предмет') . ' - CS2 Marketplace')

@section('content')
@include('partials.breadcrumbs', [
    'title' => $listing->inventory_item_name ?: $listing->market_hash_name ?: 'Неизвестный предмет',
    'breadcrumbs' => [
        ['title' => 'Маркетплейс', 'url' => route('marketplace.index')],
        ['title' => $listing->inventory_item_name ?: $listing->market_hash_name ?: 'Неизвестный предмет']
    ]
])

@include('partials.categories-section')

<div id="skin-details-app" data-listing-id="{{ $listing->id }}"></div>
@endsection