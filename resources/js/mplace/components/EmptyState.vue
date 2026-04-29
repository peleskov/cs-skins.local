<template>
	<div class="empty-state">
		<div v-if="icon" class="empty-state__icon">
			<i :class="icon"></i>
		</div>
		<h6 v-if="title" class="empty-state__title">{{ title }}</h6>
		<p v-if="description" class="empty-state__desc">{{ description }}</p>
		<a v-if="buttonText && buttonHref" :href="buttonHref" class="empty-state__btn">{{ buttonText }}</a>
		<button v-else-if="buttonText" type="button" class="empty-state__btn" @click="$emit('action')">
			{{ buttonText }}
		</button>
	</div>
</template>

<script setup>
defineProps({
	icon: { type: String, default: '' },
	title: { type: String, default: '' },
	description: { type: String, default: '' },
	buttonText: { type: String, default: '' },
	buttonHref: { type: String, default: '' },
});

defineEmits(['action']);
</script>
