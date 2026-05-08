<template>
	<div v-if="lastPage > 1"
		class="pagination-control d-flex flex-wrap align-items-center justify-content-center justify-content-lg-between gap-3"
		:class="`pagination-${variant}`">
		<ul class="pagination-pages d-flex align-items-center gap-1 list-unstyled m-0 p-0">
			<li>
				<button type="button" class="page-btn" :disabled="currentPage === 1"
					@click="go(currentPage - 1)" aria-label="Назад">
					<i class="ri-arrow-left-s-line"></i>
				</button>
			</li>
			<li v-for="(p, idx) in pageNumbers" :key="idx">
				<button v-if="typeof p === 'number'" type="button" class="page-btn"
					:class="{ active: p === currentPage }" @click="go(p)">
					{{ p }}
				</button>
				<span v-else class="page-ellipsis">…</span>
			</li>
			<li>
				<button type="button" class="page-btn" :disabled="currentPage === lastPage"
					@click="go(currentPage + 1)" aria-label="Далее">
					<i class="ri-arrow-right-s-line"></i>
				</button>
			</li>
		</ul>
		<div v-if="showPerPage" class="pagination-perpage d-none d-lg-flex align-items-center gap-2 ms-auto">
			<span class="small text-muted">На странице:</span>
			<select class="form-select form-select-sm" style="width: auto;" :value="perPage"
				@change="changePerPage($event.target.value)">
				<option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
			</select>
		</div>
	</div>
</template>

<script>
export default {
	name: 'Pagination',
	props: {
		currentPage: { type: Number, required: true },
		lastPage: { type: Number, required: true },
		perPage: { type: Number, default: 25 },
		perPageOptions: { type: Array, default: () => [25, 50, 100] },
		showPerPage: { type: Boolean, default: true },
		variant: { type: String, default: 'light' }
	},
	emits: ['update:currentPage', 'update:perPage'],
	computed: {
		pageNumbers() {
			const total = this.lastPage;
			const current = this.currentPage;
			if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
			const pages = [1];
			if (current > 4) pages.push('...');
			const start = Math.max(2, current - 1);
			const end = Math.min(total - 1, current + 1);
			for (let i = start; i <= end; i++) pages.push(i);
			if (current < total - 3) pages.push('...');
			pages.push(total);
			return pages;
		}
	},
	methods: {
		go(page) {
			if (typeof page !== 'number' || page < 1 || page > this.lastPage || page === this.currentPage) return;
			this.$emit('update:currentPage', page);
		},
		changePerPage(value) {
			const v = parseInt(value) || this.perPageOptions[0];
			this.$emit('update:perPage', v);
		}
	}
};
</script>

<style scoped>
.pagination-control {
	padding-top: 8px;
}

.pagination-pages {
	flex-wrap: wrap;
}

.page-btn {
	min-width: 36px;
	height: 36px;
	padding: 0 10px;
	background: #fff;
	border: 1px solid #e2e8f0;
	border-radius: 8px;
	color: #475569;
	font: 600 13px "Inter", sans-serif;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
	cursor: pointer;
}

.page-btn:hover:not(:disabled):not(.active) {
	background: #f1f5f9;
	color: #0f172a;
}

.page-btn.active,
.page-btn.active:hover {
	background: #ff8d2f !important;
	border-color: #ff8d2f !important;
	color: #fff !important;
}

.page-btn:disabled {
	opacity: 0.4;
	cursor: not-allowed;
}

.page-btn i {
	font-size: 18px;
	line-height: 1;
}

.page-ellipsis {
	padding: 0 6px;
	color: #94a3b8;
}

/* Dark variant — для секции кейсов */
.pagination-dark .page-btn {
	background: rgba(255, 255, 255, 0.04);
	border-color: rgba(255, 255, 255, 0.08);
	color: #d1d5db;
}

.pagination-dark .page-btn:hover:not(:disabled):not(.active) {
	background: rgba(255, 140, 0, 0.12);
	border-color: rgba(255, 140, 0, 0.3);
	color: #fff;
}

.pagination-dark .page-btn.active,
.pagination-dark .page-btn.active:hover {
	background: #ff8c00 !important;
	border-color: #ff8c00 !important;
	color: #000 !important;
}

.pagination-dark .page-ellipsis {
	color: rgba(255, 255, 255, 0.45);
}

.pagination-dark :deep(.form-select) {
	background-color: rgba(255, 255, 255, 0.04);
	border-color: rgba(255, 255, 255, 0.1);
	color: #fff;
}

.pagination-dark .text-muted {
	color: rgba(255, 255, 255, 0.55) !important;
}
</style>
