<!-- page head section starts -->
<section class="page-head-section">
    <div class="container page-heading">
        <h2 class="h3 mb-3 text-white text-center">{{ $title }}</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb flex-lg-nowrap justify-content-center justify-content-lg-star">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}"><i class="ri-home-line"></i>Главная</a>
                </li>
                @if(isset($breadcrumbs) && count($breadcrumbs) > 0)
                    @foreach($breadcrumbs as $breadcrumb)
                        @if(!$loop->last)
                            <li class="breadcrumb-item">
                                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['title'] }}</a>
                            </li>
                        @else
                            <li class="breadcrumb-item active" aria-current="page">{{ $breadcrumb['title'] }}</li>
                        @endif
                    @endforeach
                @else
                    <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
                @endif
            </ol>
        </nav>
    </div>
</section>
<!-- page head section end -->