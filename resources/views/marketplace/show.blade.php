@extends('layouts.app')

@section('title', $listing->item->name_ru . ' - CS2 Marketplace')

@section('content')
@include('partials.breadcrumbs', [
    'title' => $listing->item->name_ru,
    'breadcrumbs' => [
        ['title' => 'Маркетплейс', 'url' => route('marketplace.index')],
        ['title' => $listing->item->name_ru]
    ]
])

@include('partials.categories-section')

<div id="skin-details-app" data-listing-id="{{ $listing->id }}"></div>
@endsection