@auth('client')
@php($_client = auth('client')->user())
<div id="cases-mobile-balance-app"
    data-user="{{ json_encode([
        'balance' => $_client->balance ?? 0,
        'bonus_balance' => $_client->bonus_balance ?? 0,
    ]) }}"
    data-routes="{{ json_encode([
        'profile' => route('profile'),
    ]) }}"></div>
@endauth
