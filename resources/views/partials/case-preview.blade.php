@if(isset($cases) && $cases->count() > 0)
<section class="banner-section section-b-space">
    <div class="container">
        <div class="title text-center d-none d-lg-block">
            <h2>{{ __('home.cases') }}</h2>
            <div class="loader-line" style="left: calc(50% - 40px);"></div>
        </div>
        <div class="position-relative">
            <div class="swiper banner1-slider">
                <div class="swiper-wrapper">
                    @foreach($cases as $case)
                    <div class="swiper-slide">
                        <div class="case-banner-part d-flex flex-column justify-content-end" style="background-image: url('{{ Storage::url($case->image_url) }}');">
                            <div class="case-banner-text">
                                <p class="fw-semibold dark-text">{{ $case->name }}</p>
                                @if($case->description)
                                <p class="small dark-text">{{ $case->description }}</p>
                                @endif
                            </div>
                            <a href="{{ route('cases.show', $case->slug) }}" class="stretched-link"></a>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        <!-- Кнопка для перехода на страницу кейсов -->
        <div class="text-center mt-4">
            <a href="{{ route('cases.index') }}" class="btn theme-btn">
                {{ __('home.view_all_cases') }}
            </a>
        </div>
    </div>
</section>
@endif