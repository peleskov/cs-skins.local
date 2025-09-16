<template>
	<section class="popular-restaurant banner-section section-b-space ratio3_2 overflow-hidden bg-white">
		<div class="container">
			<div class="title text-center">
				<h2>Кейсы</h2>
				<div class="loader-line" style="left: calc(50% - 40px);"></div>
				<div class="sub-title">
					<p>Откройте кейсы и получите ценные предметы CS2</p>
				</div>
			</div>

			<!-- Контейнер для кейсов -->
			<div class="row g-4 justify-content-center">
				<div v-for="case_item in cases" :key="case_item.id" class="col-lg-3 col-md-4 col-sm-6">
					<div class="vertical-product-box">
						<div class="vertical-product-box-img">
							<a :href="`/cases/${case_item.slug}`">
								<img class="product-img-top w-100 bg-img"
									:src="case_item.image_url ? `/storage/${case_item.image_url}` : '/images/case-placeholder.png'"
									:alt="case_item.name" @error="handleImageError">
							</a>
							<div class="offers">
								<div class="d-flex align-items-center justify-content-between">
									<h4 v-html="formatPrice(case_item.price)"></h4>
								</div>
							</div>
						</div>
						<div class="vertical-product-body">
							<div class="d-flex flex-column mt-sm-3 mt-2 mb-2">
								<a :href="`/cases/${case_item.slug}`">
									<h4 class="vertical-product-title">{{ case_item.name }}</h4>
								</a>
								<p v-if="case_item.description" class="text-muted small mb-2">
									{{ case_item.description.length > 100 ? case_item.description.substring(0, 100) +
										'...' : case_item.description }}
								</p>
							</div>
							<div class="pt-sm-3 pt-2">
								<a :href="`/cases/${case_item.slug}`" class="btn theme-btn w-100">
									Открыть кейс
								</a>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Сообщение если кейсов нет -->
			<div v-if="!cases || cases.length === 0" class="text-center py-5">
				<div class="empty-state">
					<i class="ri-archive-line fs-1 text-muted mb-3"></i>
					<h4>Кейсы не найдены</h4>
					<p class="text-muted">В данный момент нет доступных кейсов для открытия.</p>
				</div>
			</div>
		</div>
	</section>
</template>

<script>
import { formatPrice } from '../utils/helpers';

export default {
	name: 'Cases',
	setup() {
		return { formatPrice };
	},
	props: {
		initialCases: {
			type: Array,
			default: () => []
		}
	},
	data() {
		return {
			cases: this.initialCases || []
		};
	},
	methods: {
		handleImageError(event) {
			event.target.src = '/images/case-placeholder.png';
		},

		handleCurrencyChange() {
			// Принудительно обновляем данные для пересчета цен
			if (this.cases.length > 0) {
				this.cases = [...this.cases];
			}
		}
	},
	mounted() {
		console.log('Cases component mounted with', this.cases.length, 'cases');
		
		// Слушаем события смены валюты
		window.addEventListener('currency-changed', this.handleCurrencyChange);
	},

	beforeUnmount() {
		// Убираем слушатели при размонтировании
		window.removeEventListener('currency-changed', this.handleCurrencyChange);
	}
}
</script>