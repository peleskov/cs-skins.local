<template>
	<div class="login-history-content">
		<!-- Не премиум -->
		<div v-if="!isPremium" class="text-center py-5">
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
				<template v-for="month in visibleMonths" :key="month">
					<li>
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
				</template>
			</ul>

			<!-- Пагинация -->
			<nav v-if="totalPages > 1" class="custom-pagination">
				<ul class="pagination justify-content-center">
					<li class="page-item" :class="{ disabled: page === 1 }">
						<a class="page-link" href="#!" tabindex="-1" @click.prevent="page > 1 && page--">
							<i class="ri-arrow-left-s-line"></i>
						</a>
					</li>
					<li v-for="p in totalPages" :key="p" class="page-item" :class="{ active: page === p }">
						<a class="page-link" href="#!" @click.prevent="page = p">{{ p }}</a>
					</li>
					<li class="page-item" :class="{ disabled: page === totalPages }">
						<a class="page-link" href="#!" @click.prevent="page < totalPages && page++">
							<i class="ri-arrow-right-s-line"></i>
						</a>
					</li>
				</ul>
			</nav>
		</template>
	</div>
</template>

<script>
const MONTHS = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
const PAGE_LIMIT = 30;

export default {
	name: 'ProfileLoginHistory',
	props: {
		client: { type: Object, required: true },
		dateRange: { type: Array, default: null }
	},
	data() {
		return {
			loading: true,
			history: [],
			isPremium: false,
			page: 1
		};
	},
	computed: {
		filteredHistory() {
			if (!this.dateRange || !this.dateRange[0] || !this.dateRange[1]) return this.history;
			const from = new Date(this.dateRange[0]);
			const to = new Date(this.dateRange[1]);
			from.setHours(0, 0, 0, 0);
			to.setHours(23, 59, 59, 999);
			return this.history.filter(entry => {
				const parts = entry.date.split('.');
				const d = new Date(parts[2].split(' ')[0], parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
				const time = parts[2].split(' ')[1];
				if (time) {
					const [h, m, s] = time.split(':');
					d.setHours(h, m, s);
				}
				return d >= from && d <= to;
			});
		},
		grouped() {
			const groups = {};
			for (const entry of this.filteredHistory) {
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
		},
		pages() {
			const result = [];
			let current = [];
			let count = 0;
			for (const month of this.monthKeys) {
				const entries = this.grouped[month].length;
				if (current.length > 0 && count + entries > PAGE_LIMIT) {
					result.push(current);
					current = [];
					count = 0;
				}
				current.push(month);
				count += entries;
			}
			if (current.length) result.push(current);
			return result;
		},
		totalPages() {
			return this.pages.length;
		},
		visibleMonths() {
			return this.pages[this.page - 1] || [];
		}
	},
	watch: {
		dateRange() {
			this.page = 1;
		}
	},
	async mounted() {
		await this.loadHistory();
	},
	methods: {
		async loadHistory() {
			this.loading = true;
			try {
				const res = await fetch('/api/subscription/login-history');
				const data = await res.json();
				if (data.success) {
					this.isPremium = true;
					this.history = data.data;
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
