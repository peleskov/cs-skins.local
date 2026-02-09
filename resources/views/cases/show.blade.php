@extends('layouts.cases')

@section('title', $case->name . ' - Кейсы CS2')

@section('content')
@include('partials.cases.carousel-winner')
<div
    data-vue-component="case-details"
    data-initial-case="{{ json_encode($caseData) }}"
    data-case-slug="{{ $case->slug }}"
    data-routes="{{ json_encode(['cases' => route('cases.index'), 'faq' => route('faq')]) }}"
></div>
@endsection