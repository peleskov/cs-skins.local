@extends('layouts.app')

@section('title', 'Кейсы - CS2 Скины')

@section('content')
@include('partials.breadcrumbs', ['title' => 'Кейсы'])
@include('partials.categories-section')
@include('partials.cases-section')
@endsection