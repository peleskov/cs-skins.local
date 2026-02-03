<!-- Header section start -->
<header class="header px-3" data-vue-component="header"
    data-user="{{ auth('client')->check() ? json_encode([
        'id' => auth('client')->user()->id,
        'name' => auth('client')->user()->name,
        'steam_avatar' => auth('client')->user()->steam_avatar,
        'balance' => auth('client')->user()->balance ?? 0,
        'bonus_balance' => auth('client')->user()->bonus_balance ?? 0
     ]) : 'null' }}"
    data-routes="{{ json_encode([
        'home' => route('home'),
        'marketplace' => route('marketplace.index'),
        'auctions' => route('auctions.index'),
        'cases' => auth('client')->check() ? route('cases.index') : null,
        'caseInventory' => auth('client')->check() ? route('case-inventory.index') : '#',
        'upgrade' => auth('client')->check() ? route('upgrade.index') : '#',
        'profile' => auth('client')->check() ? route('profile') : '#',
        'faq' => route('faq'),
        'contact' => route('contact'),
        'login' => route('auth.steam'),
        'logout' => route('auth.logout')
        ])
    }}"
    data-logo-url="{{ asset('images/logo_white.svg') }}"
    data-logo-ico="{{ asset('images/logo_ico.svg') }}">
</header>
<!-- Header Section end -->