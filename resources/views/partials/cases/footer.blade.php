<!-- footer section starts -->
<footer class="footer-section">
    <div class="container">
        <div class="main-footer">
            <div class="row g-3">
                <div class="col-xl-4 col-lg-12">
                    <div class="footer-logo-part">
                        <img class="img-fluid logo" src="{{ asset('images/logo_white.svg') }}?v={{ filemtime(public_path('images/logo_white.svg')) }}" alt="logo">
                        <p>{{ $footerData['company']['name'] }}</p>
                        <p>{{ $footerData['company']['inn'] }}</p>
                        <p>{{ $footerData['company']['address'] }}</p>
                        <p>Email: <a href="mailto:{{ $footerData['company']['email'] }}" class="footer-link">{{ $footerData['company']['email'] }}</a></p>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="row g-3">
                        <div class="col-4 col-sm-6 col-12">
                            <div>
                                <h5 class="footer-title">{{ $footerData['documents_title'] }}</h5>
                                <ul class="content">
                                    @foreach(\App\Models\Doc::all() as $doc)
                                        <li>
                                            <a class="nav-links" href="{{ route('doc', $doc->slug) }}"><h6>{{ $doc->title }}</h6></a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="col-4 col-sm-6 col-12">
                            <div>
                                <h5 class="footer-title">{{ $footerData['useful_links_title'] }}</h5>
                                <ul class="content">
                                    <li>
                                        <a class="nav-links" href="{{ route('faq') }}"><h6>{{ __('navigation.main.faq') }}</h6></a>
                                    </li>
                                    <li>
                                        <a class="nav-links" href="{{ route('contact') }}"><h6>{{ __('navigation.main.contact') }}</h6></a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="bottom-footer-part">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6>{{ $footerData['copyright'] }}</h6>
                <img class="img-fluid cards" src="{{ asset('images/icons/footer-card.png') }}" alt="card">
            </div>
        </div>
    </div>
</footer>
<!-- footer section end -->