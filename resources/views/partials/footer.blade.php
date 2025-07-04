<!-- footer section starts -->
<footer class="footer-section">
    <div class="container">
        <div class="main-footer">
            <div class="row g-3">
                <div class="col-xl-4 col-lg-12">
                    <div class="footer-logo-part">
                        <img class="img-fluid logo" src="{{ asset('images/logo_white.svg') }}" alt="logo">
                        <p>ООО "Скинс"</p>
                        <p>ИНН: 1234567890</p>
                        <p>Адрес: г. Москва, ул. Примерная, д. 1</p>
                        <p>Email: <a href="mailto:info@cs-skins.pro" class="footer-link">info@cs-skins.pro</a></p>
                        <div class="social-media-part">
                            <ul class="social-icon">
                                <li>
                                    <a href="https://www.facebook.com/login/">
                                        <i class="ri-vk-fill icon"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="row g-3">
                        <div class="col-4 col-sm-6 col-12">
                            <div>
                                <h5 class="footer-title">Документы</h5>
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
                                <h5 class="footer-title">Полезные ссылки</h5>
                                <ul class="content">
                                    <li>
                                        <a class="nav-links" href="{{ route('faq') }}"><h6>FAQ</h6></a>
                                    </li>
                                    <li>
                                        <a class="nav-links" href="{{ route('contact') }}"><h6>Контакты</h6></a>
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
                <h6>@ Copyright 2025 CS-SCINS.PRO. Все права защищены.</h6>
                <img class="img-fluid cards" src="{{ asset('images/icons/footer-card.png') }}" alt="card">
            </div>
        </div>
    </div>
</footer>
<!-- footer section end -->