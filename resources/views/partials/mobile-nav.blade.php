<!-- Mobile Header + Drawer (≤1023px) -->
<div id="mobile-header-app"
    data-user="{{ auth('client')->check() ? json_encode([
        'id' => auth('client')->user()->id,
        'name' => auth('client')->user()->name,
        'steam_avatar' => auth('client')->user()->steam_avatar,
        'balance' => auth('client')->user()->balance ?? 0,
        'avatar_border_color' => auth('client')->user()->avatar_border_color,
        'nickname_color' => auth('client')->user()->nickname_color,
        'is_premium' => auth('client')->user()->isPremium()
     ]) : 'null' }}"
    data-routes="{{ json_encode([
        'home' => route('home'),
        'cart' => route('cart'),
        'marketplace' => route('marketplace.index'),
        'auctions' => route('auctions.index'),
        'cases' => auth('client')->check() ? route('cases.index') : '#',
        'profile' => auth('client')->check() ? route('profile') : '#',
        'faq' => route('faq'),
        'contact' => route('contact'),
        'login' => route('auth.steam'),
        'logout' => route('auth.logout')
     ]) }}"
    data-logo-url="{{ asset('images/logo_white.svg') }}?v={{ filemtime(public_path('images/logo_white.svg')) }}"
    data-cart-count="{{ app('App\Services\CartService')->getCount() }}">
</div>

<!-- Mobile Bottom Nav (≤1023px, fixed) -->
<div id="mobile-bottom-nav-app"
    data-user="{{ auth('client')->check() ? json_encode([
        'id' => auth('client')->user()->id,
        'name' => auth('client')->user()->name,
     ]) : 'null' }}"
    data-routes="{{ json_encode([
        'marketplace' => route('marketplace.index'),
        'auctions' => route('auctions.index'),
        'cases' => auth('client')->check() ? route('cases.index') : '#',
        'cart' => route('cart'),
        'profile' => auth('client')->check() ? route('profile') : '#',
     ]) }}"
    data-cart-count="{{ app('App\Services\CartService')->getCount() }}">
</div>
