@extends('layouts.app')

@section('title', 'Профиль')

@section('content')
<!-- page head section starts -->
<section class="page-head-section">
    <div class="container page-heading">
        <h2 class="h3 mb-3 text-white text-center">Профиль</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb flex-lg-nowrap justify-content-center justify-content-lg-star">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}"><i class="ri-home-line"></i>Главная</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Профиль</li>
            </ol>
        </nav>
    </div>
</section>
<!-- page head section end -->

<!-- profile section starts -->
<section class="profile-section section-b-space">
    <div class="container">
        <div class="row g-3">
            <div class="col-lg-3">
                <div class="profile-sidebar sticky-top">
                    <div class="profile-cover">
                        <img class="img-fluid profile-pic" src="{{ $client->steam_avatar ?? '/images/icons/p5.png' }}" alt="profile">
                    </div>
                    <div class="profile-name">
                        <h5 class="user-name">{{ $client->name }}</h5>
                        <h6>{{ $client->email ?? 'Email не указан' }}</h6>
                    </div>
                    <ul class="profile-list">
                        <li class="active">
                            <i class="ri-user-3-line"></i>
                            <a href="{{ route('profile') }}">Профиль</a>
                        </li>
                        <li>
                            <i class="ri-shopping-bag-3-line"></i>
                            <a href="#">Торговля</a>
                        </li>
                        <li>
                            <i class="ri-store-2-line"></i>
                            <a href="#">Мои аукционы</a>
                        </li>
                        <li>
                            <i class="ri-bank-card-line"></i>
                            <a href="#">Баланс</a>
                        </li>
                        <li>
                            <i class="ri-settings-3-line"></i>
                            <a href="#">Настройки</a>
                        </li>
                        <li>
                            <i class="ri-logout-box-r-line"></i>
                            <a href="#log-out" data-bs-toggle="modal">Выйти</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-9">
                <div class="change-profile-content">
                    <div class="title">
                        <div class="loader-line"></div>
                        <h3>Информация профиля</h3>
                    </div>
                    <ul class="profile-details-list">
                        <li>
                            <div class="profile-content">
                                <div class="d-flex align-items-center gap-sm-2 gap-1">
                                    <i class="ri-user-3-fill"></i>
                                    <span>Имя :</span>
                                </div>
                                <h6>{{ $client->name }}</h6>
                            </div>
                        </li>
                        <li>
                            <div class="profile-content">
                                <div class="d-flex align-items-center gap-sm-2 gap-1">
                                    <i class="ri-mail-fill"></i>
                                    <span>Email :</span>
                                </div>
                                <h6>
                                    {{ $client->email ?? 'Не указан' }}
                                    @if($client->email)
                                        @if($client->hasVerifiedEmail())
                                            <span class="badge bg-success-subtle ms-2">Подтвержден</span>
                                        @else
                                            <span class="badge bg-warning ms-2">Не подтвержден</span>
                                        @endif
                                    @endif
                                </h6>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="#email" class="btn theme-outline" data-bs-toggle="modal">
                                    {{ $client->email ? 'Изменить' : 'Добавить' }}
                                </a>
                                @if($client->email && !$client->hasVerifiedEmail())
                                    @php
                                        $canResend = $client->canResendVerificationEmail();
                                        $secondsLeft = $client->secondsUntilCanResend();
                                    @endphp
                                    <div class="d-flex flex-column gap-1">
                                        <button class="btn theme-outline" id="resend-email-btn" 
                                                data-seconds="{{ $secondsLeft }}"
                                                data-url="{{ route('profile.resend.verification') }}"
                                                @if(!$canResend && $secondsLeft > 0) disabled @endif>
                                            <span class="btn-text">
                                                @if($canResend || $secondsLeft <= 0)
                                                    Отправить письмо
                                                @else
                                                    Отправить повторно через <span id="timer-text">{{ $client->formattedTimeUntilCanResend() }}</span>
                                                @endif
                                            </span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </li>
                        <li>
                            <div class="profile-content">
                                <div class="d-flex align-items-center gap-sm-2 gap-1">
                                    <i class="ri-gamepad-line"></i>
                                    <span>Steam ID :</span>
                                </div>
                                <h6>{{ $client->steam_id }}</h6>
                            </div>
                        </li>
                        <li>
                            <div class="profile-content">
                                <div class="d-flex align-items-center gap-sm-2 gap-1">
                                    <i class="ri-exchange-line"></i>
                                    <span>Trade URL :</span>
                                </div>
                                <h6>
                                    @if($client->steam_trade_url)
                                        <span class="trade-url-text" data-url="{{ $client->steam_trade_url }}" 
                                              style="cursor: pointer;" title="Нажмите для копирования">
                                            {{ Str::limit($client->steam_trade_url, 50) }}
                                            <i class="ri-file-copy-line ms-1"></i>
                                        </span>
                                        <span class="badge bg-success-subtle ms-2">Активен</span>
                                    @else
                                        Не указан
                                    @endif
                                </h6>
                            </div>
                            <a href="#trade-url" class="btn theme-outline mt-0" data-bs-toggle="modal">{{ $client->steam_trade_url ? 'Изменить' : 'Добавить' }}</a>
                        </li>
                        <li>
                            <div class="profile-content">
                                <div class="d-flex align-items-center gap-sm-2 gap-1">
                                    <i class="ri-wallet-3-line"></i>
                                    <span>Баланс :</span>
                                </div>
                                <h6>{{ number_format($client->balance, 2) }} ₽</h6>
                            </div>
                            <a href="#" class="btn theme-outline mt-0">Пополнить</a>
                        </li>
                        <li>
                            <div class="profile-content">
                                <div class="d-flex align-items-center gap-sm-2 gap-1">
                                    <i class="ri-shield-check-line"></i>
                                    <span>Верификация :</span>
                                </div>
                                <h6>{{ $client->is_verified ? 'Пройдена' : 'Не пройдена' }}</h6>
                            </div>
                            @if(!$client->is_verified)
                            <a href="#" class="btn theme-outline mt-0">Пройти</a>
                            @endif
                        </li>
                        <li>
                            <div class="profile-content">
                                <div class="d-flex align-items-center gap-sm-2 gap-1">
                                    <i class="ri-calendar-line"></i>
                                    <span>Дата регистрации :</span>
                                </div>
                                <h6>{{ $client->created_at->format('d.m.Y') }}</h6>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- profile section end -->

<!-- logout modal starts -->
<div class="modal address-details-modal fade" id="log-out" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="exampleModalLabel">Выход</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Вы уверены, что хотите выйти?</p>
            </div>
            <div class="modal-footer">
                <button class="btn gray-btn mt-0" data-bs-dismiss="modal">Отмена</button>
                <a href="{{ route('auth.logout') }}" class="btn theme-btn mt-0">Выйти</a>
            </div>
        </div>
    </div>
</div>
<!-- logout modal end -->

<!-- email modal starts -->
<div class="modal address-details-modal fade" id="email" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('profile.update.email') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="emailModalLabel">{{ $client->email ? 'Изменить Email' : 'Добавить Email' }}</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="email-input" class="form-label">Email адрес</label>
                        <input type="email" class="form-control" id="email-input" name="email" 
                               value="{{ $client->email }}" 
                               placeholder="example@mail.com" required>
                        <small class="text-muted">На этот адрес будут приходить важные уведомления</small>
                        @if($client->email)
                        <small class="text-warning d-block mt-2">
                            <i class="ri-information-line"></i> При изменении email потребуется повторная верификация
                        </small>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn gray-btn mt-0" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn theme-btn mt-0">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- email modal end -->

<!-- trade url modal starts -->
<div class="modal address-details-modal fade" id="trade-url" tabindex="-1" aria-labelledby="tradeUrlModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('profile.update.trade-url') }}" method="POST" id="trade-url-form">
                @csrf
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="tradeUrlModalLabel">{{ $client->steam_trade_url ? 'Изменить Trade URL' : 'Добавить Trade URL' }}</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="trade-url-input" class="form-label">Steam Trade URL</label>
                        <input type="url" class="form-control" id="trade-url-input" name="trade_url" 
                               value="{{ $client->steam_trade_url }}" 
                               placeholder="https://steamcommunity.com/tradeoffer/new/?partner=123456&token=abcdef" required>
                        <small class="text-muted">
                            Найдите Trade URL в настройках Steam: Настройки → Конфиденциальность → Торговые предложения
                        </small>
                        <div id="trade-url-validation" class="mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn gray-btn mt-0" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn theme-btn mt-0" id="save-trade-url-btn" disabled>Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- trade url modal end -->
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const resendBtn = document.getElementById('resend-email-btn');
    if (!resendBtn) return;
    
    let seconds = parseInt(resendBtn.dataset.seconds) || 0;
    const btnText = resendBtn.querySelector('.btn-text');
    let timerInterval = null;
    
    // Функция форматирования времени
    const formatTime = (sec) => {
        if (sec <= 0) return '';
        const minutes = Math.floor(sec / 60);
        const remainingSeconds = sec % 60;
        if (minutes > 0) {
            return `${minutes} мин ${remainingSeconds} сек`;
        }
        return `${remainingSeconds} сек`;
    };
    
    // Функция обновления таймера
    const updateTimer = () => {
        if (seconds <= 0) {
            // Активируем кнопку
            resendBtn.disabled = false;
            btnText.textContent = 'Отправить письмо';
            clearInterval(timerInterval);
        } else {
            // Обновляем текст
            btnText.innerHTML = `Отправить повторно через <span id="timer-text">${formatTime(seconds)}</span>`;
            seconds--; // Просто уменьшаем на 1
        }
    };
    
    // Запускаем таймер если нужно
    if (seconds > 0 && resendBtn.disabled) {
        timerInterval = setInterval(updateTimer, 1000);
    }
    
    // Обработчик клика
    resendBtn.addEventListener('click', async function(e) {
        e.preventDefault();
        
        if (resendBtn.disabled) return;
        
        // Блокируем кнопку
        resendBtn.disabled = true;
        const originalText = btnText.textContent;
        btnText.textContent = 'Отправка...';
        
        try {
            const response = await fetch(resendBtn.dataset.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
            
            const data = await response.json();
            
            if (response.ok) {
                // Успешно отправлено
                seconds = 60; // 1 минута
                btnText.innerHTML = `Отправить повторно через <span id="timer-text">${formatTime(seconds)}</span>`;
                
                // Показываем сообщение об успехе
                showNotification('success', data.message || 'Письмо отправлено');
                
                // Запускаем таймер
                timerInterval = setInterval(updateTimer, 1000);
            } else {
                // Ошибка
                showNotification('error', data.message || 'Ошибка при отправке');
                resendBtn.disabled = false;
                btnText.textContent = originalText;
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('error', 'Произошла ошибка');
            resendBtn.disabled = false;
            btnText.textContent = originalText;
        }
    });
});

// Функция показа уведомлений
function showNotification(type, message) {
    // Используем Bootstrap toast или alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    // Автоматически скрываем через 5 секунд
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Trade URL валидация
document.addEventListener('DOMContentLoaded', function() {
    const tradeUrlInput = document.getElementById('trade-url-input');
    const validationDiv = document.getElementById('trade-url-validation');
    const saveBtn = document.getElementById('save-trade-url-btn');
    const userSteamId = '{{ $client->steam_id }}';
    
    if (tradeUrlInput) {
        tradeUrlInput.addEventListener('input', function() {
            validateTradeUrl(this.value);
        });
        
        // Валидация при загрузке если есть значение
        if (tradeUrlInput.value) {
            validateTradeUrl(tradeUrlInput.value);
        }
    }
    
    function validateTradeUrl(url) {
        if (!url) {
            showValidation('', '');
            return;
        }
        
        // Проверка формата
        const pattern = /^https:\/\/steamcommunity\.com\/tradeoffer\/new\/\?partner=\d+&token=[a-zA-Z0-9_-]+$/;
        
        if (!pattern.test(url)) {
            showValidation('error', 'Неверный формат Trade URL');
            return;
        }
        
        // Извлечение Steam ID32 из URL
        const partnerMatch = url.match(/partner=(\d+)/);
        if (!partnerMatch) {
            showValidation('error', 'Не удалось найти partner ID в URL');
            return;
        }
        
        const steamId32 = partnerMatch[1];
        // Конвертация в Steam ID64
        const steamId64 = (BigInt(steamId32) + BigInt('76561197960265728')).toString();
        
        // Проверка соответствия Steam ID
        if (steamId64 !== userSteamId) {
            showValidation('error', 'Trade URL не соответствует вашему Steam ID');
            return;
        }
        
        showValidation('success', 'Trade URL корректен');
    }
    
    function showValidation(type, message) {
        if (!message) {
            validationDiv.innerHTML = '';
            saveBtn.disabled = true;
            return;
        }
        
        const className = type === 'success' ? 'text-success' : 'text-danger';
        const icon = type === 'success' ? 'ri-check-line' : 'ri-error-warning-line';
        
        validationDiv.innerHTML = `<small class="${className}"><i class="${icon}"></i> ${message}</small>`;
        saveBtn.disabled = type !== 'success';
    }
});

// Копирование Trade URL в буфер
document.addEventListener('DOMContentLoaded', function() {
    const tradeUrlText = document.querySelector('.trade-url-text');
    
    if (tradeUrlText) {
        tradeUrlText.addEventListener('click', async function() {
            const url = this.dataset.url;
            
            try {
                await navigator.clipboard.writeText(url);
                showNotification('success', 'Trade URL скопирован в буфер обмена');
                
                // Временно меняем иконку
                const icon = this.querySelector('i');
                const originalClass = icon.className;
                icon.className = 'ri-check-line ms-1 text-success';
                
                setTimeout(() => {
                    icon.className = originalClass;
                }, 2000);
                
            } catch (err) {
                showNotification('error', 'Не удалось скопировать ссылку');
                console.error('Failed to copy: ', err);
            }
        });
    }
});
</script>
@endpush