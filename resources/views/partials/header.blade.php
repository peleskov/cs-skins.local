<!-- Header section start -->
<header>
    <div class="container">
        <nav class="navbar navbar-expand-lg p-0">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#offcanvasNavbar">
                <span class="navbar-toggler-icon">
                    <i class="ri-menu-line"></i>
                </span>
            </button>
            <a href="{{ route('home') }}">
                <img class="img-fluid logo" src="{{ asset('images/logo_white.svg') }}" alt="logo">
            </a>
            <div class="nav-option order-md-2">
                <div class="dropdown-button">
                    <div class="cart-button">
                        <span>0</span>
                        <i class="ri-shopping-cart-line text-white cart-bag"></i>
                    </div>
                    <div class="onhover-box">
                        <p>Пока в корзине пусто</p>
                    </div>
                </div>
                @auth('client')
                    <div class="profile-part dropdown-button order-md-2">
                        <img class="img-fluid profile-pic" src="{{ auth('client')->user()->steam_avatar }}" alt="profile" style="width: 40px; height: 40px; border-radius: 50%;">
                        <div>
                            <h6 class="fw-normal">Привет,</h6>
                            <h5 class="fw-medium">{{ auth('client')->user()->name }}</h5>
                        </div>
                        <div class="onhover-box onhover-sm">
                            <ul class="menu-list">
                                <li>
                                    <a class="dropdown-item" href="{{ route('profile') }}#profile">
                                        <i class="ri-user-3-line me-2"></i>Профиль
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('profile') }}#trading">
                                        <i class="ri-shopping-bag-3-line me-2"></i>Торговля
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('profile') }}#inventory">
                                        <i class="ri-treasure-map-line me-2"></i>Инвентарь
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('profile') }}#auctions">
                                        <i class="ri-store-2-line me-2"></i>Мои аукционы
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('profile') }}#balance">
                                        <i class="ri-bank-card-line me-2"></i>Баланс
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('profile') }}#settings">
                                        <i class="ri-settings-3-line me-2"></i>Настройки
                                    </a>
                                </li>
                            </ul>
                            <div class="bottom-btn">
                                <a href="{{ route('auth.logout') }}" class="theme-color fw-medium d-flex"><i
                                        class="ri-logout-box-r-line me-2"></i>Выйти</a>
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ route('auth.steam') }}" class="btn btn-sm theme-btn">
                        <i class="ri-steam-fill me-1"></i>Войти через Steam
                    </a>
                @endauth
            </div>
            <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasNavbar">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="offcanvasNavbarLabel">Menu</h5>
                    <button class="navbar-toggler btn-close" id="offcanvas-close"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="navbar-nav justify-content-center flex-grow-1">
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('marketplace.index') }}">Маркетплейс</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Кейсы</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Аукцион</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('faq') }}">FAQ</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('contact') }}">Контакты</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>
</header>
<!-- Header Section end -->