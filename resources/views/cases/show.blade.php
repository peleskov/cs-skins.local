@extends('layouts.app')

@section('title', $case->name . ' - Кейсы CS2')

@section('content')
@include('partials.breadcrumbs', [
    'title' => $case->name,
    'breadcrumbs' => [
        ['title' => 'Кейсы', 'url' => route('cases.index')],
        ['title' => $case->name]
    ]
])

<div 
    id="case-details-app"
    data-case="{{ json_encode($caseData) }}"
    data-case-slug="{{ $case->slug }}"
></div>
@endsection