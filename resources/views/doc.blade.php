@extends('layouts.mplace')

@section('title', $doc->title . ' - CS2 Скины')

@section('content')
@include('partials.breadcrumbs', [
    'title' => $doc->title,
    'breadcrumbs' => [
        ['title' => 'Документы', 'url' => route('doc', \App\Models\Doc::first()->slug)],
        ['title' => $doc->title, 'url' => '']
    ]
])

<!-- blog section stars -->
<section class="section-b-space">
    <div class="container">
        <div class="blog-boxs">
            <div class="row g-3">
                <div class="col-lg-3 order-lg-0 order-1">
                    <div class="left-box wow fadeInUp">
                        <div class="shop-left-sidebar">
                            <h3 class="sidebar-title mb-3">Документы</h3>
                            <ul class="category-list custom-padding custom-height scroll-bar">
                                @foreach(\App\Models\Doc::all() as $docItem)
                                <li class="{{ $docItem->slug === $doc->slug ? 'active' : '' }}">
                                    <a href="{{ route('doc', $docItem->slug) }}">
                                        <div class="form-check ps-0 m-0 category-list-box">
                                            <div class="form-check-label">
                                                <span class="name">{{ $docItem->title }}</span>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-9">
                    <div class="blog-wrap">
                        <!-- Blog Section Start -->
                        <div class="blog-box blog-detail ratio_40">
                            <div class="content-box">
                                {!! $doc->content !!}
                            </div>
                        </div>
                        <!-- Blog Section End -->
                    </div>
                    <!-- Comment Section End -->
                </div>
            </div>
        </div>
    </div>
</section>
<!-- blog section end -->

@endsection