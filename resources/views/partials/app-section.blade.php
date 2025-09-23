@if($adBanner)
<section class="app-section section-b-space">
    <div class="container">
        <div class="d-flex align-items-center">
            <div class="app-img">
                @if($adBanner->image)
                    <img class="img-fluid phone" src="{{ Storage::url($adBanner->image) }}" alt="{{ $adBanner->title }}">
                @endif
            </div>
            <div class="app-content">
                @if($adBanner->title)
                    <h2 class="dark-text">{{ $adBanner->title }}</h2>
                @endif

                @if($adBanner->text)
                    <h5 class="dark-text">{{ $adBanner->text }}</h5>
                @endif
            </div>
        </div>
    </div>
</section>
@endif