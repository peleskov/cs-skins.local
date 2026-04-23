<template>
	<div class="mobile-home-filters">
		<form class="mobile-search" @submit.prevent="submit">
			<input v-model="query" type="search" class="mobile-search-input w-100" placeholder="Поиск" autocomplete="off">
		</form>

		<div class="mobile-categories d-flex gap-2 mt-3 overflow-auto">
			<button type="button" class="mobile-cat-pill" :class="{ active: !activeType }" @click="select('')">Все</button>
			<button v-for="c in categories" :key="c.type" type="button" class="mobile-cat-pill"
				:class="{ active: activeType === c.type }" @click="select(c.type)">
				{{ c.name }}
			</button>
		</div>
	</div>
</template>

<script>
import axios from 'axios';

export default {
	name: 'MobileHomeFilters',
	props: {
		marketplaceUrl: { type: String, required: true }
	},
	data() {
		return {
			query: '',
			categories: [],
			activeType: ''
		};
	},
	methods: {
		async loadCategories() {
			try {
				const { data } = await axios.get('/api/marketplace/categories');
				this.categories = Array.isArray(data) ? data : [];
			} catch (e) {
				console.error('Не удалось загрузить категории:', e);
			}
		},
		saveAndGo(patch) {
			let current = {};
			try {
				current = JSON.parse(localStorage.getItem('marketplace_filters') || '{}');
			} catch (_) { }
			localStorage.setItem('marketplace_filters', JSON.stringify({ ...current, ...patch }));
			window.location.href = this.marketplaceUrl;
		},
		submit() {
			this.saveAndGo({ search: this.query.trim() });
		},
		select(type) {
			this.activeType = type;
			this.saveAndGo({ types: type });
		}
	},
	mounted() {
		this.loadCategories();
	}
};
</script>
