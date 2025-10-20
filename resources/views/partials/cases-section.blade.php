<div
    id="cases-app"
    data-cases="{{ json_encode($cases) }}"
    data-user="{{ Auth::guard('client')->check() ? json_encode(Auth::guard('client')->user()->only(['id', 'name', 'balance', 'steam_avatar'])) : 'null' }}"
></div>