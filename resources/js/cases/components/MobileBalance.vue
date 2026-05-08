<template>
	<div class="cases-mobile-balance d-lg-none" v-if="user">
		<div class="cmb-row">
			<div class="cmb-card">
				<div class="cmb-label">БОНУСНЫЙ</div>
				<div class="cmb-value">
					{{ Number(bonusBalance).toFixed(2) }} <span class="cmb-cur">$</span>
				</div>
			</div>
			<div class="cmb-card">
				<div class="cmb-label">БАЛАНС</div>
				<div class="cmb-value" v-html="mainBalanceFormatted"></div>
			</div>
		</div>
		<div class="cmb-row">
			<a :href="routes.profile + '#balance'" class="cmb-btn cmb-btn-primary">
				<i class="m-ico m-ico-plus"></i><span>ПОПОЛНИТЬ</span>
			</a>
			<a :href="routes.profile + '#trade-url'" class="cmb-btn cmb-btn-secondary">
				<i class="m-ico m-ico-link"></i><span>ТРЕЙД ССЫЛКА</span>
			</a>
		</div>
	</div>
</template>

<script>
import { formatPrice } from '../../shared/utils/helpers';

export default {
	name: 'MobileBalance',
	setup() {
		return { formatPrice };
	},
	props: {
		user: { type: Object, default: null },
		routes: { type: Object, required: true }
	},
	computed: {
		mainBalanceFormatted() {
			const html = formatPrice(this.mainBalance, 'RUB', false, true, true);
			return String(html).replace(/(&nbsp;)([^&]+)$/, '$1<span class="cmb-cur">$2</span>');
		}
	},
	data() {
		return {
			mainBalance: this.user?.balance || 0,
			bonusBalance: this.user?.bonus_balance || 0,
			isRubleCurrency: this.checkIsRuble()
		};
	},
	methods: {
		checkIsRuble() {
			try {
				const saved = localStorage.getItem('selectedCurrency');
				if (!saved) return true;
				const currency = JSON.parse(saved);
				return !currency || currency.code === 'RUB';
			} catch {
				return true;
			}
		},
		handleCurrencyChange() {
			this.isRubleCurrency = this.checkIsRuble();
			this.$forceUpdate();
		},
		handleBalanceUpdate(event) {
			if (event.detail) {
				this.mainBalance = event.detail.main ?? this.mainBalance;
				this.bonusBalance = event.detail.bonus ?? this.bonusBalance;
			}
		}
	},
	mounted() {
		window.addEventListener('currency-changed', this.handleCurrencyChange);
		window.addEventListener('balance-updated', this.handleBalanceUpdate);
	},
	beforeUnmount() {
		window.removeEventListener('currency-changed', this.handleCurrencyChange);
		window.removeEventListener('balance-updated', this.handleBalanceUpdate);
	}
};
</script>
