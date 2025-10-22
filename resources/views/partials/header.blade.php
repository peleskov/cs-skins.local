<!-- Header section start -->
<div id="header-app"
    data-user="{{ auth('client')->check() ? json_encode([
        'id' => auth('client')->user()->id,
        'name' => auth('client')->user()->name,
        'steam_avatar' => auth('client')->user()->steam_avatar,
        'balance' => auth('client')->user()->balance ?? 0
     ]) : 'null' }}"
    data-routes="{{ json_encode([
        'home' => route('home'),
        'cart' => route('cart'),
        'marketplace' => route('marketplace.index'),
        'auctions' => route('auctions.index'),
        'cases' => auth('client')->check() ? route('cases.index') : null,
        'profile' => auth('client')->check() ? route('profile') : '#',
        'faq' => route('faq'),
        'contact' => route('contact'),
        'login' => route('auth.steam'),
        'logout' => route('auth.logout')
        ]) 
    }}"
    data-logo-url="{{ asset('images/logo_white.svg') }}"
    data-cart-count="{{ app('App\Services\CartService')->getCount() }}"
    data-favorites-count="{{ auth('client')->check() ? auth('client')->user()->favorites()->whereHas('listing', function($query) { $query->where('status', 'active'); })->count() : 0 }}"
    data-extension-download-url="{{ env('EXTENSION_DOWNLOAD_URL') }}">
</div>
<!-- Header Section end -->