<template>
	<div class="change-profile-content">
		<div class="title">
			<div class="loader-line"></div>
			<h3>Мои аукционы</h3>
		</div>
		<div class="text-center py-5">
			<i class="ri-store-2-line display-4 text-muted mb-3"></i>
			<h4>Система аукционов</h4>
			<p class="text-muted">Здесь вы сможете создавать и управлять своими аукционами.<br>
			Функция находится в разработке и будет доступна в ближайшее время.</p>
			<div class="mt-4">
				<div class="alert alert-info">
					<i class="ri-information-line me-2"></i>
					<strong>Планируемые возможности:</strong>
					<ul class="list-unstyled mt-2 mb-0">
						<li>• Создание аукционов с начальной ставкой</li>
						<li>• Управление длительностью торгов</li>
						<li>• Автоматическое завершение аукционов</li>
						<li>• История ваших аукционов</li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'ProfileAuctions',
	props: {
		client: {
			type: Object,
			required: true
		}
	}
}
</script>