<template>
	<div class="dropdown currency-selector">
		<button class="currency-display dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
			<span class="currency-symbol flag-icon" :class="`flag-${selectedLanguage?.code || 'ru'}`"></span>
			<span class="currency-code">{{ selectedLanguage?.name || 'RU' }}</span>
			<i class="ri-arrow-down-s-line"></i>
		</button>
		<ul class="dropdown-menu currency-dropdown">
			<li v-for="language in languages" :key="language.code">
				<a
					href="#"
					@click.prevent="selectLanguage(language)"
					class="dropdown-item currency-item"
					:class="{ 'active': selectedLanguage?.code === language.code }"
				>
					<span class="currency-symbol me-2 flag-icon" :class="`flag-${language.code}`"></span>
					<span class="currency-code">{{ language.fullName }}</span>
				</a>
			</li>
		</ul>
	</div>
</template>

<script>
export default {
	name: 'LanguageSelector',
	data() {
		return {
			languages: [
				{ code: 'ru', name: 'RU', fullName: 'Русский' },
				{ code: 'en', name: 'EN', fullName: 'English' }
			],
			selectedLanguage: null
		}
	},
	mounted() {
		this.loadSelectedLanguage();
	},
	methods: {
		loadSelectedLanguage() {
			// Получаем текущий язык из localStorage или используем русский по умолчанию
			const saved = localStorage.getItem('selectedLanguage');
			const currentLocale = document.documentElement.lang || 'ru';

			if (saved) {
				try {
					const savedLanguage = JSON.parse(saved);
					const language = this.languages.find(l => l.code === savedLanguage.code);
					if (language) {
						this.selectedLanguage = language;
						return;
					}
				} catch (error) {
					console.error('Error parsing saved language:', error);
				}
			}

			// Используем текущий язык из HTML или русский по умолчанию
			this.selectedLanguage = this.languages.find(l => l.code === currentLocale.substring(0, 2))
				|| this.languages[0];
		},

		async selectLanguage(language) {
			this.selectedLanguage = language;
			localStorage.setItem('selectedLanguage', JSON.stringify(language));

			// Отправляем запрос на сервер для смены языка
			try {
				const response = await fetch(`/locale/${language.code}`, {
					method: 'GET',
					headers: {
						'X-Requested-With': 'XMLHttpRequest'
					}
				});

				if (response.ok) {
					// Перезагружаем страницу для применения нового языка
					window.location.reload();
				}
			} catch (error) {
				console.error('Error changing language:', error);
			}
		}
	}
}
</script>