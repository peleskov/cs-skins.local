<!-- Header section start -->
<div id="header-app" 
     data-user="{{ auth('client')->check() ? json_encode([
         'name' => auth('client')->user()->name,
         'steam_avatar' => auth('client')->user()->steam_avatar
     ]) : 'null' }}"
     data-routes="{{ json_encode([
         'home' => route('home'),
         'cart' => route('cart'),
         'marketplace' => route('marketplace.index'),
         'profile' => auth('client')->check() ? route('profile') : '#',
         'faq' => route('faq'),
         'contact' => route('contact'),
         'login' => route('auth.steam'),
         'logout' => route('auth.logout')
     ]) }}"
     data-logo-url="{{ asset('images/logo_white.svg') }}"
     data-cart-count="{{ app('App\Services\CartService')->getCount() }}">
</div>
<!-- Header Section end -->