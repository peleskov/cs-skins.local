<template>
	<div class="dropdown-button currency-selector">
		<div class="currency-display">
			<span class="currency-symbol">{{ selectedCurrency?.symbol || '₽' }}</span>
			<span class="currency-code">{{ selectedCurrency?.code || 'RUB' }}</span>
			<i class="ri-arrow-down-s-line"></i>
		</div>
		<div class="onhover-box onhover-sm currency-dropdown">
			<div v-if="isLoading" class="loading-message">
				Загружаем валюты...
			</div>
			<div v-else>
				<ul class="menu-list">
					<li v-for="currency in currencies" :key="currency.code">
						<a 
							href="#" 
							@click.prevent="selectCurrency(currency)"
							class="dropdown-item currency-item"
							:class="{ 'active': selectedCurrency?.code === currency.code }"
						>
							<span class="currency-symbol me-2">{{ currency.symbol }}</span>
							<span class="currency-code">{{ currency.code }}</span>
						</a>
					</li>
				</ul>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'CurrencySelector',
	data() {
		return {
			currencies: [],
			selectedCurrency: null,
			isLoading: false
		}
	},
	async mounted() {
		await this.loadCurrencies();
		this.loadSelectedCurrency();
	},
	methods: {
		async loadCurrencies() {
			this.isLoading = true;
			try {
				const response = await fetch('/api/currencies');
				if (response.ok) {
					this.currencies = await response.json();
					
					// Если сохраненная валюта существует, обновляем её курс
					const saved = localStorage.getItem('selectedCurrency');
					if (saved) {
						try {
							const savedCurrency = JSON.parse(saved);
							const updatedCurrency = this.currencies.find(c => c.code === savedCurrency.code);
							if (updatedCurrency) {
								localStorage.setItem('selectedCurrency', JSON.stringify(updatedCurrency));
								this.selectedCurrency = updatedCurrency;
							}
						} catch (error) {
							console.error('Error updating saved currency:', error);
						}
					}
				}
			} catch (error) {
				console.error('Error loading currencies:', error);
			} finally {
				this.isLoading = false;
			}
		},

		loadSelectedCurrency() {
			const saved = localStorage.getItem('selectedCurrency');
			if (saved) {
				try {
					const savedCurrency = JSON.parse(saved);
					const currency = this.currencies.find(c => c.code === savedCurrency.code);
					if (currency) {
						this.selectedCurrency = currency;
					}
				} catch (error) {
					console.error('Error parsing saved currency:', error);
				}
			}
		},

		selectCurrency(currency) {
			this.selectedCurrency = currency;
			localStorage.setItem('selectedCurrency', JSON.stringify(currency));
			
			// Обновляем кэш курсов валют для корректной работы formatPrice
			if (window.currencyRatesCache) {
				const index = window.currencyRatesCache.findIndex(c => c.code === currency.code);
				if (index !== -1) {
					window.currencyRatesCache[index] = currency;
				} else {
					window.currencyRatesCache.push(currency);
				}
			}
			
			// Эмитим событие для других компонентов
			window.dispatchEvent(new CustomEvent('currency-changed', {
				detail: { currency }
			}));
		}
	}
}
</script>