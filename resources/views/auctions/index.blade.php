@extends('layouts.app')

@section('title', 'Аукционы - CS2 Скины')

@section('content')
@include('partials.breadcrumbs', ['title' => 'Аукционы'])

<div 
    id="auctions-app"
    data-auctions="{{ json_encode($featuredAuctions) }}"
    data-total="{{ $totalAuctions }}"
    data-has-more="{{ $hasMorePages ? 'true' : 'false' }}"
    data-current-user="{{ auth('client')->check() ? json_encode([
        'id' => auth('client')->user()->id,
        'name' => auth('client')->user()->name,
        'steam_avatar' => auth('client')->user()->steam_avatar
    ]) : 'null' }}"
></div>
@endsection