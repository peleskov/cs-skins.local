<template>
	<div class="login-history-content">
		<!-- Не премиум -->
		<div v-if="!isPremium && !loading" class="text-center py-5">
			<p class="text-muted">Доступно только для PREMIUM-пользователей</p>
		</div>

		<!-- Загрузка -->
		<div v-else-if="loading" class="text-center py-5">
			<div class="spinner-border text-primary" role="status"></div>
		</div>

		<!-- Пустая история -->
		<div v-else-if="!monthKeys.length" class="text-center py-5">
			<p class="text-muted">Записей пока нет</p>
		</div>

		<!-- Группировка по месяцам -->
		<template v-else>
			<ul class="profile-history-list">
				<li v-for="month in monthKeys" :key="month">
					<h6 class="mb-2">{{ month }}</h6>
					<ul class="login-entries-list d-flex flex-column gap-2">
						<li v-for="(entry, index) in grouped[month]" :key="index" class="ps-4">
							<div class="d-flex align-items-center">
								<div class="d-flex align-items-center gap-2">
									<i
										:class="entry.status === 'success' ? 'ri-checkbox-circle-fill text-success' : 'ri-close-circle-fill text-danger'"></i>
									<span>{{ entry.date }}</span>
									<span>{{ entry.device }}</span>
									<span>{{ entry.ip }}</span>
								</div>
							</div>
						</li>
					</ul>
				</li>
			</ul>

			<Pagination
				:current-page="page"
				:last-page="lastPage"
				:per-page="perPage"
				class="mt-3"
				@update:current-page="goToPage"
				@update:per-page="changePerPage" />
		</template>
	</div>
</template>

<script>
import Pagination from '../../../shared/components/Pagination.vue';

const MONTHS = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];

export default {
	name: 'ProfileLoginHistory',
	components: { Pagination },
	props: {
		client: { type: Object, required: true },
		dateRange: { type: Array, default: null }
	},
	data() {
		return {
			loading: true,
			history: [],
			isPremium: false,
			page: 1,
			perPage: 25,
			lastPage: 1,
			total: 0
		};
	},
	computed: {
		grouped() {
			const groups = {};
			for (const entry of this.history) {
				const parts = entry.date.split('.');
				const monthIndex = parseInt(parts[1], 10) - 1;
				const year = parts[2]?.split(' ')[0];
				const key = `${MONTHS[monthIndex]} ${year}`;
				if (!groups[key]) groups[key] = [];
				groups[key].push(entry);
			}
			return groups;
		},
		monthKeys() {
			return Object.keys(this.grouped);
		}
	},
	watch: {
		dateRange() {
			this.page = 1;
			this.loadHistory();
		}
	},
	async mounted() {
		await this.loadHistory();
	},
	methods: {
		goToPage(page) { this.page = page; this.loadHistory(); },
		changePerPage(value) { this.perPage = value; this.page = 1; this.loadHistory(); },

		async loadHistory() {
			this.loading = true;
			try {
				const params = new URLSearchParams({ page: this.page, per_page: this.perPage });
				if (this.dateRange?.[0]) params.append('from', this.dateRange[0]);
				if (this.dateRange?.[1]) params.append('to', this.dateRange[1]);
				const res = await fetch(`/api/subscription/login-history?${params}`);
				const data = await res.json();
				if (data.success) {
					this.isPremium = true;
					this.history = data.data;
					if (data.pagination) {
						this.page = data.pagination.current_page;
						this.lastPage = data.pagination.last_page;
						this.total = data.pagination.total;
					}
				} else {
					this.isPremium = false;
				}
			} catch (e) {
				console.error('Ошибка загрузки истории заходов', e);
			} finally {
				this.loading = false;
			}
		}
	}
};
</script>
