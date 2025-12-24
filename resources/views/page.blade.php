@extends('layouts.mplace')

@section('title', ($page->meta_title ?? $page->title) . ' - CS2 Скины')

@if($page->meta_description)
@section('meta_description', $page->meta_description)
@endif

@section('content')
@include('partials.breadcrumbs', ['title' => $page->title])

<!-- page content section starts -->
<section class="section-b-space">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="blog-boxs">
                    <div class="blog-wrap">
                        <!-- Page Content Section Start -->
                        <div class="blog-box blog-detail">
                            <div class="content-box">
                                {!! $page->content !!}
                            </div>
                        </div>
                        <!-- Page Content Section End -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- page content section end -->
@endsection