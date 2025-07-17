// Скрипт для работы счетчика корзины в header
document.addEventListener('DOMContentLoaded', function() {
    // Ждем пока skeleton loader исчезнет и появится настоящий header
    function initializeCartHeader() {
        const cartCountEl = document.querySelector('.main-header-cart');
        const cartEmptyMessage = document.querySelector('.cart-empty-message');
        const cartItems = document.querySelector('.cart-items');
        const cartTotal = document.querySelector('.cart-total');
        const cartActions = document.querySelector('.cart-actions');
        
        if (!cartCountEl) {
            // Если элемент еще не найден, попробуем через 100ms
            setTimeout(initializeCartHeader, 100);
            return;
        }
        
        console.log('Cart header initialized with element:', cartCountEl);

        // Загрузка счетчика корзины
        async function loadCartCount() {
            try {
                const response = await fetch('/api/cart/count', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const data = await response.json();

                if (data.success) {
                    updateCartCount(data.count);
                }
            } catch (error) {
                console.error('Error loading cart count:', error);
            }
        }

        // Загрузка превью корзины для dropdown
        async function loadCartPreview() {
            try {
                const response = await fetch('/api/cart', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const data = await response.json();

                if (data.success) {
                    updateCartDropdown(data.data);
                }
            } catch (error) {
                console.error('Error loading cart preview:', error);
                if (cartEmptyMessage) {
                    cartEmptyMessage.textContent = 'Ошибка загрузки корзины';
                }
            }
        }

        // Обновление счетчика корзины
        function updateCartCount(count) {
            console.log('Updating cart count to:', count, 'Element found:', !!cartCountEl);
            if (cartCountEl) {
                cartCountEl.textContent = count;
                cartCountEl.style.display = count > 0 ? 'inline' : 'none';
                console.log('Cart count element updated');
            } else {
                console.error('Cart count element not found');
            }
        }

        // Обновление dropdown корзины
        function updateCartDropdown(cartData) {
            const { items, total, count } = cartData;

            if (count === 0) {
                if (cartEmptyMessage) {
                    cartEmptyMessage.textContent = 'Корзина пуста';
                    cartEmptyMessage.style.display = 'block';
                }
                if (cartItems) cartItems.style.display = 'none';
                if (cartTotal) cartTotal.style.display = 'none';
                if (cartActions) cartActions.style.display = 'none';
            } else {
                if (cartEmptyMessage) cartEmptyMessage.style.display = 'none';
                if (cartItems) cartItems.style.display = 'block';
                if (cartTotal) cartTotal.style.display = 'block';
                if (cartActions) cartActions.style.display = 'block';

                // Показываем первые 3 товара
                if (cartItems) {
                    const itemsToShow = items.slice(0, 3);
                    cartItems.innerHTML = itemsToShow.map(item => `
                        <div class="cart-item-preview d-flex align-items-center mb-2">
                            <img src="${item.item.image_url}" alt="${item.item.name}" style="width: 40px; height: 30px; object-fit: contain;" class="me-2">
                            <div class="flex-grow-1">
                                <div class="cart-item-name text-truncate" style="font-size: 12px;">${item.item.name}</div>
                                <div class="cart-item-price text-muted" style="font-size: 11px;">${formatPrice(item.price)} ₽</div>
                            </div>
                        </div>
                    `).join('');

                    if (items.length > 3) {
                        cartItems.innerHTML += `<div class="text-muted text-center" style="font-size: 11px;">И еще ${items.length - 3} товар(ов)</div>`;
                    }
                }

                if (cartTotal) {
                    cartTotal.innerHTML = `<div class="text-center border-top pt-2"><strong>Итого: ${formatPrice(total)} ₽</strong></div>`;
                }
            }
        }

        // Форматирование цены
        function formatPrice(price) {
            return Number(price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }

        // Слушаем события обновления корзины
        window.addEventListener('cart-updated', function(event) {
            console.log('Cart updated event received:', event.detail);
            updateCartCount(event.detail.count);
            // Перезагружаем превью корзины
            loadCartPreview();
        });

        // Показываем preview при наведении на корзину
        const cartButton = document.querySelector('.cart-button');
        const cartDropdown = document.querySelector('.cart-dropdown');

        if (cartButton && cartDropdown) {
            cartButton.addEventListener('mouseenter', function() {
                loadCartPreview();
            });
        }

        // Инициализация
        loadCartCount();
    }
    
    // Запускаем инициализацию
    initializeCartHeader();
});