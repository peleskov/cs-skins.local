<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<h3>Торговля</h3>
		</div>
		<div class="text-center py-5">
			<i class="ri-shopping-bag-3-line display-4 text-muted mb-3"></i>
			<h4>Раздел торговли</h4>
			<p class="text-muted">Здесь будут отображаться ваши активные листинги и продажи боту.<br>
			Функция находится в разработке.</p>
		</div>
	</div>
</template>

<script>
export default {
	name: 'ProfileTrading',
	props: {
		client: {
			type: Object,
			required: true
		}
	}
}
</script>