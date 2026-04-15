@extends('layouts.landing')

@section('title', 'PREMIUM-подписка CS Skins')

@section('content')
<!-- page head section starts -->
<section class="page-head-section">
    <div class="container page-heading">
        <h2 class="h3 mb-3 text-white text-center">PREMIUM-подписка</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb flex-lg-nowrap justify-content-center justify-content-lg-star">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}"><i class="ri-home-line"></i>Главная</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">PREMIUM</li>
            </ol>
        </nav>
    </div>
</section>
<!-- page head section end -->

<section class="section-b-space">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="text-center">
                    <p class="mb-4" style="color:rgba(var(--content-color),1);font-size:16px;">
                        Получите доступ к эксклюзивным возможностям CS Skins
                    </p>

                    {{-- Сценарий А: кнопки-переходы --}}
                    <div class="d-flex flex-column gap-3 mb-4">
                        <a href="{{ route('home') }}" class="theme-btn btn w-100">Перейти на маркетплейс</a>
                        <a href="{{ route('cases.index') }}" class="theme-btn btn w-100">Открыть кейсы</a>
                    </div>

                    {{-- Сценарий Б: оплата подписки --}}
                    <div class="border-top pt-4" style="border-color:rgba(var(--content-color),0.2)!important;">
                        <h3 class="mb-3" style="color:rgba(var(--dark-text),1);font-size:18px;">Или оплатите подписку прямо сейчас</h3>
                        <button id="purchase-btn" class="theme-btn btn w-100">
                            Оплатить через СБП
                        </button>
                    </div>

                    {{-- Блок QR-кода (скрыт) --}}
                    <div id="payment-block" style="display:none;" class="mt-4">
                        <iframe id="payment-iframe" style="width:100%;height:800px;border:none;border-radius:8px;"></iframe>
                        <div id="payment-status" class="mt-3">
                            <p class="mb-3" style="color:rgba(var(--content-color),1);font-size:14px;">
                                <span class="spinner-border spinner-border-sm me-1"></span>
                                Ожидаем подтверждение оплаты...
                            </p>
                            <button id="force-check-btn" class="theme-btn btn">Я оплатил</button>
                        </div>
                        <div id="payment-success" style="display:none;" class="mt-3">
                            <p style="color:rgba(var(--success-color),1);font-size:14px;">
                                Оплата прошла успешно! Перенаправляем на авторизацию через Steam...
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script nonce="{{ app('csp-nonce') }}">
(function(){
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    var paymentId = null;
    var polling = null;
    var checking = false;

    function onPaid() {
        clearInterval(polling);
        document.getElementById('payment-status').style.display = 'none';
        document.getElementById('payment-success').style.display = 'block';
        setTimeout(function() {
            window.location.href = '{{ route("auth.steam") }}';
        }, 1500);
    }

    function checkStatus() {
        if (checking || !paymentId) return;
        checking = true;
        fetch('/api/partner/payment-status/' + paymentId, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.is_paid) onPaid();
        })
        .catch(function(e) { console.error('Ошибка проверки статуса', e); })
        .finally(function() { checking = false; });
    }

    document.getElementById('purchase-btn').addEventListener('click', function() {
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Создаём платёж...';

        fetch('{{ route("partner.purchase") }}', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.is_paid) {
                onPaid();
                return;
            }
            if (data.success && data.payment_url) {
                paymentId = data.payment_id;
                document.getElementById('payment-iframe').src = data.payment_url;
                document.getElementById('payment-block').style.display = 'block';
                btn.style.display = 'none';
                polling = setInterval(checkStatus, 10000);
                setTimeout(checkStatus, 5000);
            } else {
                btn.disabled = false;
                btn.textContent = 'Оплатить через СБП';
                alert(data.message || 'Ошибка создания платежа');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Оплатить через СБП';
        });
    });

    document.getElementById('force-check-btn').addEventListener('click', function() {
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Проверяем...';
        fetch('/api/partner/payment-status/' + paymentId, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.is_paid) {
                onPaid();
            } else {
                btn.disabled = false;
                btn.textContent = 'Я оплатил';
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Я оплатил';
        });
    });
})();
</script>
@endpush
