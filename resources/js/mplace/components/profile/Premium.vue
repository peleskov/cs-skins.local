<template>
	<div class="premium-content">
		<!-- Загрузка -->
		<div v-if="loading" class="text-center py-5">
			<div class="spinner-border text-primary" role="status"></div>
		</div>

		<!-- Если подписка НЕ активна — промо -->
		<div v-else-if="!subscription.is_active">
			<!-- Преимущества -->
			<div class="row g-3 mb-4">
				<div class="col-md-4">
					<div class="card benefit h-100">
						<div class="card-body d-flex gap-3">
							<img src="/images/icons/benefit_ico1.png" alt="">
							<div>
								<p class="title">-{{ ps.case_discount_low }}% на все кейсы</p>
								<p class="text-muted">Постоянная скидка на все кейсы
									до {{ ps.case_discount_threshold }}₽,
									что дороже -{{ ps.case_discount_high }}%</p>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="card benefit h-100">
						<div class="card-body d-flex gap-3">
							<img src="/images/icons/benefit_ico2.png" alt="">
							<div>
								<p class="title">Комиссия {{ ps.marketplace_fee }}%</p>
								<p class="text-muted">У всех пользователей без
									PREMIUM {{ ps.marketplace_fee_default }}%</p>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="card benefit h-100">
						<div class="card-body d-flex gap-3">
							<img src="/images/icons/benefit_ico3.png" alt="">
							<div>
								<p class="title">Вывод {{ ps.withdraw_fee }}%</p>
								<p class="text-muted">У всех пользователей без PREMIUM {{ ps.withdraw_fee_default }}%
								</p>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="card benefit h-100">
						<div class="card-body d-flex gap-3">
							<img src="/images/icons/benefit_ico4.png" alt="">
							<div>
								<p class="title">Обводка аватарки</p>
								<p class="text-muted">Премиальная рамка в чате и на маркетплейсе</p>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="card benefit h-100">
						<div class="card-body d-flex gap-3">
							<img src="/images/icons/benefit_ico5.png" alt="">
							<div>
								<p class="title">Особенный ник</p>
								<p class="text-muted">Цветной ник+ значек ВИП</p>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="card benefit h-100">
						<div class="card-body d-flex gap-3">
							<img src="/images/icons/benefit_ico6.png" alt="">
							<div>
								<p class="title">Антианлак</p>
								<p class="text-muted">В случае неудачи при открытии 10 одинаковых кейсов, следующий –
									бесплатно</p>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="card benefit h-100">
						<div class="card-body d-flex gap-3">
							<img src="/images/icons/benefit_ico7.png" alt="">
							<div>
								<p class="title">Приоритет</p>
								<p class="text-muted">В службе поддержки</p>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="card benefit h-100">
						<div class="card-body d-flex gap-3">
							<img src="/images/icons/benefit_ico8.png" alt="">
							<div>
								<p class="title">История</p>
								<p class="text-muted">Захода на аккаунт</p>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-4">
					<div class="card benefit h-100">
						<div class="card-body d-flex gap-3">
							<img src="/images/icons/benefit_ico9.png" alt="">
							<div>
								<p class="title">Быстрый вход</p>
								<p class="text-muted">Для авторизации можно поставить ввод код-пароля</p>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="text-center">
				<button class="btn theme-btn" data-bs-toggle="modal" data-bs-target="#purchasePremiumModal">ОФОРМИТЬ
					PREMIUM</button>
			</div>
		</div>

		<!-- Если подписка АКТИВНА — кабинет -->
		<div v-else>
			<ul class="profile-details-list">
				<!-- Подписка -->
				<li>
					<div class="profile-content">
						<div class="d-flex align-items-start gap-sm-2 gap-1">
							<svg class="mt-1" width="16" height="17" viewBox="0 0 16 17" fill="none"
								xmlns="http://www.w3.org/2000/svg">
								<path
									d="M7.66092 4.50382C7.76589 4.30154 8.05498 4.30154 8.15995 4.50382L9.16581 6.44035C9.20658 6.51891 9.28162 6.57425 9.36893 6.58878L11.5223 6.94621C11.7468 6.98369 11.8364 7.25854 11.6765 7.42081L10.1443 8.9755C10.0824 9.03857 10.054 9.12732 10.0672 9.21476L10.3924 11.373C10.426 11.5981 10.1918 11.7678 9.98807 11.6659L8.0369 10.6894C7.95776 10.6498 7.86411 10.6498 7.78495 10.6894L5.8328 11.6659C5.62912 11.7677 5.39577 11.5981 5.42948 11.373L5.75467 9.21476C5.76788 9.12719 5.7387 9.03859 5.67655 8.9755L4.1453 7.42081C3.98536 7.25848 4.07478 6.98352 4.29959 6.94621L6.45194 6.58878C6.5393 6.57428 6.61525 6.51895 6.65604 6.44035L7.66092 4.50382Z"
									fill="url(#paint0_linear_671_38030)" />
								<path fill-rule="evenodd" clip-rule="evenodd"
									d="M7.53397 0.145424C7.74827 -0.0484104 8.07463 -0.0485389 8.28885 0.145424L9.24686 1.01261C9.39209 1.14402 9.59512 1.19095 9.78299 1.13566L11.0223 0.769448C11.2995 0.687662 11.5941 0.82975 11.7029 1.09757L12.1892 2.29386C12.2631 2.47547 12.4264 2.60602 12.6199 2.63761L13.8953 2.84562C14.1804 2.89227 14.3839 3.14712 14.366 3.43546L14.2849 4.7255C14.2727 4.92116 14.3636 5.10921 14.5242 5.2216L15.5828 5.96183C15.8195 6.12756 15.8921 6.44611 15.7508 6.69816L15.118 7.82511C15.0221 7.9961 15.022 8.20494 15.118 8.37589L15.7508 9.50187C15.8922 9.75385 15.8194 10.0724 15.5828 10.2382L14.5242 10.9794C14.3635 11.0918 14.2726 11.2798 14.2849 11.4755L14.366 12.7646C14.3841 13.053 14.1805 13.3087 13.8953 13.3554L12.6199 13.5634C12.4264 13.595 12.2631 13.7255 12.1892 13.9071L11.7029 15.1034C11.5941 15.3712 11.2995 15.5133 11.0223 15.4316L9.78299 15.0653C9.59523 15.0101 9.39209 15.0562 9.24686 15.1874L8.28885 16.0556C8.07468 16.2494 7.74823 16.2492 7.53397 16.0556L6.57596 15.1874C6.43066 15.056 6.22678 15.0099 6.03885 15.0653L4.79959 15.4316C4.52245 15.5131 4.22875 15.3711 4.11991 15.1034L3.63358 13.9071C3.55981 13.7256 3.39624 13.5951 3.20292 13.5634L1.92752 13.3554C1.64214 13.3089 1.4387 13.0531 1.45682 12.7646L1.53788 11.4755C1.55017 11.2799 1.45917 11.0919 1.29862 10.9794L0.240025 10.2382C0.00318809 10.0724 -0.0694757 9.75396 0.0720558 9.50187L0.703892 8.37589C0.799853 8.20492 0.79983 7.99609 0.703892 7.82511L0.0720558 6.69816C-0.0694351 6.44603 0.00311388 6.12756 0.240025 5.96183L1.29862 5.2216C1.45918 5.10921 1.55007 4.9211 1.53788 4.7255L1.45682 3.43546C1.43886 3.14701 1.64224 2.89213 1.92752 2.84562L3.20292 2.63761C3.39627 2.6059 3.5598 2.4754 3.63358 2.29386L4.11991 1.09757C4.22874 0.829845 4.52241 0.687805 4.79959 0.769448L6.03885 1.13566C6.2269 1.19112 6.43064 1.14423 6.57596 1.01261L7.53397 0.145424ZM7.91092 2.89738C5.03749 2.89758 2.7078 5.22702 2.7078 8.1005C2.70789 10.9739 5.03755 13.3034 7.91092 13.3036C10.7844 13.3035 13.114 10.974 13.114 8.1005C13.114 5.22697 10.7844 2.89749 7.91092 2.89738Z"
									fill="url(#paint1_linear_671_38030)" />
								<defs>
									<linearGradient id="paint0_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
									<linearGradient id="paint1_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
								</defs>
							</svg>
							<div class="d-flex flex-column">
								<span>Подписка</span>
								<small class="text-muted">Активна до {{ formatDate(subscription.expires_at) }}</small>
							</div>
						</div>
					</div>
					<div class="d-flex align-items-center gap-2">
						<button class="btn theme-outline mt-0" data-bs-toggle="modal"
							data-bs-target="#purchasePremiumModal">Продлить</button>
						<button class="btn theme-outline mt-0" data-bs-toggle="modal"
							data-bs-target="#autoRenewalModal">Автопродление</button>
					</div>
				</li>

				<!-- Код-пароль -->
				<li>
					<div class="profile-content">
						<div class="d-flex align-items-start gap-sm-2 gap-1">
							<svg class="mt-1" width="16" height="17" viewBox="0 0 16 17" fill="none"
								xmlns="http://www.w3.org/2000/svg">
								<path
									d="M7.66092 4.50382C7.76589 4.30154 8.05498 4.30154 8.15995 4.50382L9.16581 6.44035C9.20658 6.51891 9.28162 6.57425 9.36893 6.58878L11.5223 6.94621C11.7468 6.98369 11.8364 7.25854 11.6765 7.42081L10.1443 8.9755C10.0824 9.03857 10.054 9.12732 10.0672 9.21476L10.3924 11.373C10.426 11.5981 10.1918 11.7678 9.98807 11.6659L8.0369 10.6894C7.95776 10.6498 7.86411 10.6498 7.78495 10.6894L5.8328 11.6659C5.62912 11.7677 5.39577 11.5981 5.42948 11.373L5.75467 9.21476C5.76788 9.12719 5.7387 9.03859 5.67655 8.9755L4.1453 7.42081C3.98536 7.25848 4.07478 6.98352 4.29959 6.94621L6.45194 6.58878C6.5393 6.57428 6.61525 6.51895 6.65604 6.44035L7.66092 4.50382Z"
									fill="url(#paint0_linear_671_38030)" />
								<path fill-rule="evenodd" clip-rule="evenodd"
									d="M7.53397 0.145424C7.74827 -0.0484104 8.07463 -0.0485389 8.28885 0.145424L9.24686 1.01261C9.39209 1.14402 9.59512 1.19095 9.78299 1.13566L11.0223 0.769448C11.2995 0.687662 11.5941 0.82975 11.7029 1.09757L12.1892 2.29386C12.2631 2.47547 12.4264 2.60602 12.6199 2.63761L13.8953 2.84562C14.1804 2.89227 14.3839 3.14712 14.366 3.43546L14.2849 4.7255C14.2727 4.92116 14.3636 5.10921 14.5242 5.2216L15.5828 5.96183C15.8195 6.12756 15.8921 6.44611 15.7508 6.69816L15.118 7.82511C15.0221 7.9961 15.022 8.20494 15.118 8.37589L15.7508 9.50187C15.8922 9.75385 15.8194 10.0724 15.5828 10.2382L14.5242 10.9794C14.3635 11.0918 14.2726 11.2798 14.2849 11.4755L14.366 12.7646C14.3841 13.053 14.1805 13.3087 13.8953 13.3554L12.6199 13.5634C12.4264 13.595 12.2631 13.7255 12.1892 13.9071L11.7029 15.1034C11.5941 15.3712 11.2995 15.5133 11.0223 15.4316L9.78299 15.0653C9.59523 15.0101 9.39209 15.0562 9.24686 15.1874L8.28885 16.0556C8.07468 16.2494 7.74823 16.2492 7.53397 16.0556L6.57596 15.1874C6.43066 15.056 6.22678 15.0099 6.03885 15.0653L4.79959 15.4316C4.52245 15.5131 4.22875 15.3711 4.11991 15.1034L3.63358 13.9071C3.55981 13.7256 3.39624 13.5951 3.20292 13.5634L1.92752 13.3554C1.64214 13.3089 1.4387 13.0531 1.45682 12.7646L1.53788 11.4755C1.55017 11.2799 1.45917 11.0919 1.29862 10.9794L0.240025 10.2382C0.00318809 10.0724 -0.0694757 9.75396 0.0720558 9.50187L0.703892 8.37589C0.799853 8.20492 0.79983 7.99609 0.703892 7.82511L0.0720558 6.69816C-0.0694351 6.44603 0.00311388 6.12756 0.240025 5.96183L1.29862 5.2216C1.45918 5.10921 1.55007 4.9211 1.53788 4.7255L1.45682 3.43546C1.43886 3.14701 1.64224 2.89213 1.92752 2.84562L3.20292 2.63761C3.39627 2.6059 3.5598 2.4754 3.63358 2.29386L4.11991 1.09757C4.22874 0.829845 4.52241 0.687805 4.79959 0.769448L6.03885 1.13566C6.2269 1.19112 6.43064 1.14423 6.57596 1.01261L7.53397 0.145424ZM7.91092 2.89738C5.03749 2.89758 2.7078 5.22702 2.7078 8.1005C2.70789 10.9739 5.03755 13.3034 7.91092 13.3036C10.7844 13.3035 13.114 10.974 13.114 8.1005C13.114 5.22697 10.7844 2.89749 7.91092 2.89738Z"
									fill="url(#paint1_linear_671_38030)" />
								<defs>
									<linearGradient id="paint0_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
									<linearGradient id="paint1_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
								</defs>
							</svg>
							<div class="d-flex flex-column">
								<span>Код-пароль</span>
								<small class="text-muted">{{ !isFeatureEnabled('pin_code') ? 'Выключен' : (hasPinCode ?
									'Установлен' : 'Не установлен') }}</small>
							</div>
						</div>
					</div>
					<div class="d-flex align-items-center gap-2">
						<button v-if="isFeatureEnabled('pin_code')" class="btn theme-outline mt-0"
							data-bs-toggle="modal" data-bs-target="#pinCodeModal" @click="pinMode = 'set'">{{
								hasPinCode ? 'Изменить' : 'Установить' }}</button>
						<input type="checkbox" :checked="isFeatureEnabled('pin_code')"
							@change="toggleFeature('pin_code', $event.target.checked)">
					</div>
				</li>


				<!-- Скидка на кейсы -->
				<li>
					<div class="profile-content">
						<div class="d-flex align-items-start gap-sm-2 gap-1">
							<svg class="mt-1" width="16" height="17" viewBox="0 0 16 17" fill="none"
								xmlns="http://www.w3.org/2000/svg">
								<path
									d="M7.66092 4.50382C7.76589 4.30154 8.05498 4.30154 8.15995 4.50382L9.16581 6.44035C9.20658 6.51891 9.28162 6.57425 9.36893 6.58878L11.5223 6.94621C11.7468 6.98369 11.8364 7.25854 11.6765 7.42081L10.1443 8.9755C10.0824 9.03857 10.054 9.12732 10.0672 9.21476L10.3924 11.373C10.426 11.5981 10.1918 11.7678 9.98807 11.6659L8.0369 10.6894C7.95776 10.6498 7.86411 10.6498 7.78495 10.6894L5.8328 11.6659C5.62912 11.7677 5.39577 11.5981 5.42948 11.373L5.75467 9.21476C5.76788 9.12719 5.7387 9.03859 5.67655 8.9755L4.1453 7.42081C3.98536 7.25848 4.07478 6.98352 4.29959 6.94621L6.45194 6.58878C6.5393 6.57428 6.61525 6.51895 6.65604 6.44035L7.66092 4.50382Z"
									fill="url(#paint0_linear_671_38030)" />
								<path fill-rule="evenodd" clip-rule="evenodd"
									d="M7.53397 0.145424C7.74827 -0.0484104 8.07463 -0.0485389 8.28885 0.145424L9.24686 1.01261C9.39209 1.14402 9.59512 1.19095 9.78299 1.13566L11.0223 0.769448C11.2995 0.687662 11.5941 0.82975 11.7029 1.09757L12.1892 2.29386C12.2631 2.47547 12.4264 2.60602 12.6199 2.63761L13.8953 2.84562C14.1804 2.89227 14.3839 3.14712 14.366 3.43546L14.2849 4.7255C14.2727 4.92116 14.3636 5.10921 14.5242 5.2216L15.5828 5.96183C15.8195 6.12756 15.8921 6.44611 15.7508 6.69816L15.118 7.82511C15.0221 7.9961 15.022 8.20494 15.118 8.37589L15.7508 9.50187C15.8922 9.75385 15.8194 10.0724 15.5828 10.2382L14.5242 10.9794C14.3635 11.0918 14.2726 11.2798 14.2849 11.4755L14.366 12.7646C14.3841 13.053 14.1805 13.3087 13.8953 13.3554L12.6199 13.5634C12.4264 13.595 12.2631 13.7255 12.1892 13.9071L11.7029 15.1034C11.5941 15.3712 11.2995 15.5133 11.0223 15.4316L9.78299 15.0653C9.59523 15.0101 9.39209 15.0562 9.24686 15.1874L8.28885 16.0556C8.07468 16.2494 7.74823 16.2492 7.53397 16.0556L6.57596 15.1874C6.43066 15.056 6.22678 15.0099 6.03885 15.0653L4.79959 15.4316C4.52245 15.5131 4.22875 15.3711 4.11991 15.1034L3.63358 13.9071C3.55981 13.7256 3.39624 13.5951 3.20292 13.5634L1.92752 13.3554C1.64214 13.3089 1.4387 13.0531 1.45682 12.7646L1.53788 11.4755C1.55017 11.2799 1.45917 11.0919 1.29862 10.9794L0.240025 10.2382C0.00318809 10.0724 -0.0694757 9.75396 0.0720558 9.50187L0.703892 8.37589C0.799853 8.20492 0.79983 7.99609 0.703892 7.82511L0.0720558 6.69816C-0.0694351 6.44603 0.00311388 6.12756 0.240025 5.96183L1.29862 5.2216C1.45918 5.10921 1.55007 4.9211 1.53788 4.7255L1.45682 3.43546C1.43886 3.14701 1.64224 2.89213 1.92752 2.84562L3.20292 2.63761C3.39627 2.6059 3.5598 2.4754 3.63358 2.29386L4.11991 1.09757C4.22874 0.829845 4.52241 0.687805 4.79959 0.769448L6.03885 1.13566C6.2269 1.19112 6.43064 1.14423 6.57596 1.01261L7.53397 0.145424ZM7.91092 2.89738C5.03749 2.89758 2.7078 5.22702 2.7078 8.1005C2.70789 10.9739 5.03755 13.3034 7.91092 13.3036C10.7844 13.3035 13.114 10.974 13.114 8.1005C13.114 5.22697 10.7844 2.89749 7.91092 2.89738Z"
									fill="url(#paint1_linear_671_38030)" />
								<defs>
									<linearGradient id="paint0_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
									<linearGradient id="paint1_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
								</defs>
							</svg>
							<div class="d-flex flex-column">
								<span>Скидка на кейсы</span>
								<small class="text-muted">-{{ ps.case_discount_low }}% на кейсы меньше
									{{ ps.case_discount_threshold }}₽,
									-{{ ps.case_discount_high }}% на кейсы больше
									{{ ps.case_discount_threshold }}₽</small>
							</div>
						</div>
					</div>
					<input type="checkbox" :checked="isFeatureEnabled('case_discount')"
						@change="toggleFeature('case_discount', $event.target.checked)">
				</li>

				<!-- Скидка на выводы -->
				<li>
					<div class="profile-content">
						<div class="d-flex align-items-start gap-sm-2 gap-1">
							<svg class="mt-1" width="16" height="17" viewBox="0 0 16 17" fill="none"
								xmlns="http://www.w3.org/2000/svg">
								<path
									d="M7.66092 4.50382C7.76589 4.30154 8.05498 4.30154 8.15995 4.50382L9.16581 6.44035C9.20658 6.51891 9.28162 6.57425 9.36893 6.58878L11.5223 6.94621C11.7468 6.98369 11.8364 7.25854 11.6765 7.42081L10.1443 8.9755C10.0824 9.03857 10.054 9.12732 10.0672 9.21476L10.3924 11.373C10.426 11.5981 10.1918 11.7678 9.98807 11.6659L8.0369 10.6894C7.95776 10.6498 7.86411 10.6498 7.78495 10.6894L5.8328 11.6659C5.62912 11.7677 5.39577 11.5981 5.42948 11.373L5.75467 9.21476C5.76788 9.12719 5.7387 9.03859 5.67655 8.9755L4.1453 7.42081C3.98536 7.25848 4.07478 6.98352 4.29959 6.94621L6.45194 6.58878C6.5393 6.57428 6.61525 6.51895 6.65604 6.44035L7.66092 4.50382Z"
									fill="url(#paint0_linear_671_38030)" />
								<path fill-rule="evenodd" clip-rule="evenodd"
									d="M7.53397 0.145424C7.74827 -0.0484104 8.07463 -0.0485389 8.28885 0.145424L9.24686 1.01261C9.39209 1.14402 9.59512 1.19095 9.78299 1.13566L11.0223 0.769448C11.2995 0.687662 11.5941 0.82975 11.7029 1.09757L12.1892 2.29386C12.2631 2.47547 12.4264 2.60602 12.6199 2.63761L13.8953 2.84562C14.1804 2.89227 14.3839 3.14712 14.366 3.43546L14.2849 4.7255C14.2727 4.92116 14.3636 5.10921 14.5242 5.2216L15.5828 5.96183C15.8195 6.12756 15.8921 6.44611 15.7508 6.69816L15.118 7.82511C15.0221 7.9961 15.022 8.20494 15.118 8.37589L15.7508 9.50187C15.8922 9.75385 15.8194 10.0724 15.5828 10.2382L14.5242 10.9794C14.3635 11.0918 14.2726 11.2798 14.2849 11.4755L14.366 12.7646C14.3841 13.053 14.1805 13.3087 13.8953 13.3554L12.6199 13.5634C12.4264 13.595 12.2631 13.7255 12.1892 13.9071L11.7029 15.1034C11.5941 15.3712 11.2995 15.5133 11.0223 15.4316L9.78299 15.0653C9.59523 15.0101 9.39209 15.0562 9.24686 15.1874L8.28885 16.0556C8.07468 16.2494 7.74823 16.2492 7.53397 16.0556L6.57596 15.1874C6.43066 15.056 6.22678 15.0099 6.03885 15.0653L4.79959 15.4316C4.52245 15.5131 4.22875 15.3711 4.11991 15.1034L3.63358 13.9071C3.55981 13.7256 3.39624 13.5951 3.20292 13.5634L1.92752 13.3554C1.64214 13.3089 1.4387 13.0531 1.45682 12.7646L1.53788 11.4755C1.55017 11.2799 1.45917 11.0919 1.29862 10.9794L0.240025 10.2382C0.00318809 10.0724 -0.0694757 9.75396 0.0720558 9.50187L0.703892 8.37589C0.799853 8.20492 0.79983 7.99609 0.703892 7.82511L0.0720558 6.69816C-0.0694351 6.44603 0.00311388 6.12756 0.240025 5.96183L1.29862 5.2216C1.45918 5.10921 1.55007 4.9211 1.53788 4.7255L1.45682 3.43546C1.43886 3.14701 1.64224 2.89213 1.92752 2.84562L3.20292 2.63761C3.39627 2.6059 3.5598 2.4754 3.63358 2.29386L4.11991 1.09757C4.22874 0.829845 4.52241 0.687805 4.79959 0.769448L6.03885 1.13566C6.2269 1.19112 6.43064 1.14423 6.57596 1.01261L7.53397 0.145424ZM7.91092 2.89738C5.03749 2.89758 2.7078 5.22702 2.7078 8.1005C2.70789 10.9739 5.03755 13.3034 7.91092 13.3036C10.7844 13.3035 13.114 10.974 13.114 8.1005C13.114 5.22697 10.7844 2.89749 7.91092 2.89738Z"
									fill="url(#paint1_linear_671_38030)" />
								<defs>
									<linearGradient id="paint0_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
									<linearGradient id="paint1_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
								</defs>
							</svg>
							<div class="d-flex flex-column">
								<span>Скидка на выводы</span>
								<small class="text-muted">Комиссия {{ ps.withdraw_fee }}% вместо
									{{ ps.withdraw_fee_default }}%</small>
							</div>
						</div>
					</div>
					<input type="checkbox" :checked="isFeatureEnabled('withdraw_fee')"
						@change="toggleFeature('withdraw_fee', $event.target.checked)">
				</li>

				<!-- Цвет обводки аватарки -->
				<li>
					<div class="profile-content">
						<div class="d-flex align-items-start gap-sm-2 gap-1">
							<svg class="mt-1" width="16" height="17" viewBox="0 0 16 17" fill="none"
								xmlns="http://www.w3.org/2000/svg">
								<path
									d="M7.66092 4.50382C7.76589 4.30154 8.05498 4.30154 8.15995 4.50382L9.16581 6.44035C9.20658 6.51891 9.28162 6.57425 9.36893 6.58878L11.5223 6.94621C11.7468 6.98369 11.8364 7.25854 11.6765 7.42081L10.1443 8.9755C10.0824 9.03857 10.054 9.12732 10.0672 9.21476L10.3924 11.373C10.426 11.5981 10.1918 11.7678 9.98807 11.6659L8.0369 10.6894C7.95776 10.6498 7.86411 10.6498 7.78495 10.6894L5.8328 11.6659C5.62912 11.7677 5.39577 11.5981 5.42948 11.373L5.75467 9.21476C5.76788 9.12719 5.7387 9.03859 5.67655 8.9755L4.1453 7.42081C3.98536 7.25848 4.07478 6.98352 4.29959 6.94621L6.45194 6.58878C6.5393 6.57428 6.61525 6.51895 6.65604 6.44035L7.66092 4.50382Z"
									fill="url(#paint0_linear_671_38030)" />
								<path fill-rule="evenodd" clip-rule="evenodd"
									d="M7.53397 0.145424C7.74827 -0.0484104 8.07463 -0.0485389 8.28885 0.145424L9.24686 1.01261C9.39209 1.14402 9.59512 1.19095 9.78299 1.13566L11.0223 0.769448C11.2995 0.687662 11.5941 0.82975 11.7029 1.09757L12.1892 2.29386C12.2631 2.47547 12.4264 2.60602 12.6199 2.63761L13.8953 2.84562C14.1804 2.89227 14.3839 3.14712 14.366 3.43546L14.2849 4.7255C14.2727 4.92116 14.3636 5.10921 14.5242 5.2216L15.5828 5.96183C15.8195 6.12756 15.8921 6.44611 15.7508 6.69816L15.118 7.82511C15.0221 7.9961 15.022 8.20494 15.118 8.37589L15.7508 9.50187C15.8922 9.75385 15.8194 10.0724 15.5828 10.2382L14.5242 10.9794C14.3635 11.0918 14.2726 11.2798 14.2849 11.4755L14.366 12.7646C14.3841 13.053 14.1805 13.3087 13.8953 13.3554L12.6199 13.5634C12.4264 13.595 12.2631 13.7255 12.1892 13.9071L11.7029 15.1034C11.5941 15.3712 11.2995 15.5133 11.0223 15.4316L9.78299 15.0653C9.59523 15.0101 9.39209 15.0562 9.24686 15.1874L8.28885 16.0556C8.07468 16.2494 7.74823 16.2492 7.53397 16.0556L6.57596 15.1874C6.43066 15.056 6.22678 15.0099 6.03885 15.0653L4.79959 15.4316C4.52245 15.5131 4.22875 15.3711 4.11991 15.1034L3.63358 13.9071C3.55981 13.7256 3.39624 13.5951 3.20292 13.5634L1.92752 13.3554C1.64214 13.3089 1.4387 13.0531 1.45682 12.7646L1.53788 11.4755C1.55017 11.2799 1.45917 11.0919 1.29862 10.9794L0.240025 10.2382C0.00318809 10.0724 -0.0694757 9.75396 0.0720558 9.50187L0.703892 8.37589C0.799853 8.20492 0.79983 7.99609 0.703892 7.82511L0.0720558 6.69816C-0.0694351 6.44603 0.00311388 6.12756 0.240025 5.96183L1.29862 5.2216C1.45918 5.10921 1.55007 4.9211 1.53788 4.7255L1.45682 3.43546C1.43886 3.14701 1.64224 2.89213 1.92752 2.84562L3.20292 2.63761C3.39627 2.6059 3.5598 2.4754 3.63358 2.29386L4.11991 1.09757C4.22874 0.829845 4.52241 0.687805 4.79959 0.769448L6.03885 1.13566C6.2269 1.19112 6.43064 1.14423 6.57596 1.01261L7.53397 0.145424ZM7.91092 2.89738C5.03749 2.89758 2.7078 5.22702 2.7078 8.1005C2.70789 10.9739 5.03755 13.3034 7.91092 13.3036C10.7844 13.3035 13.114 10.974 13.114 8.1005C13.114 5.22697 10.7844 2.89749 7.91092 2.89738Z"
									fill="url(#paint1_linear_671_38030)" />
								<defs>
									<linearGradient id="paint0_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
									<linearGradient id="paint1_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
								</defs>
							</svg>
							<div class="d-flex flex-column">
								<span>Цвет обводки аватарки</span>
								<small class="text-muted">{{ avatarBorderColor || 'Не установлен' }}</small>
							</div>
						</div>
					</div>
					<div class="d-flex align-items-center gap-2">
						<span v-if="avatarBorderColor" class="avatar-color-preview"
							:style="{ background: avatarBorderColor }"></span>
						<button class="btn theme-outline mt-0" data-bs-toggle="modal"
							data-bs-target="#avatarColorModal">Выбрать</button>
					</div>
				</li>

				<!-- Цвет никнейма -->
				<li>
					<div class="profile-content">
						<div class="d-flex align-items-start gap-sm-2 gap-1">
							<svg class="mt-1" width="16" height="17" viewBox="0 0 16 17" fill="none"
								xmlns="http://www.w3.org/2000/svg">
								<path
									d="M7.66092 4.50382C7.76589 4.30154 8.05498 4.30154 8.15995 4.50382L9.16581 6.44035C9.20658 6.51891 9.28162 6.57425 9.36893 6.58878L11.5223 6.94621C11.7468 6.98369 11.8364 7.25854 11.6765 7.42081L10.1443 8.9755C10.0824 9.03857 10.054 9.12732 10.0672 9.21476L10.3924 11.373C10.426 11.5981 10.1918 11.7678 9.98807 11.6659L8.0369 10.6894C7.95776 10.6498 7.86411 10.6498 7.78495 10.6894L5.8328 11.6659C5.62912 11.7677 5.39577 11.5981 5.42948 11.373L5.75467 9.21476C5.76788 9.12719 5.7387 9.03859 5.67655 8.9755L4.1453 7.42081C3.98536 7.25848 4.07478 6.98352 4.29959 6.94621L6.45194 6.58878C6.5393 6.57428 6.61525 6.51895 6.65604 6.44035L7.66092 4.50382Z"
									fill="url(#paint0_linear_671_38030)" />
								<path fill-rule="evenodd" clip-rule="evenodd"
									d="M7.53397 0.145424C7.74827 -0.0484104 8.07463 -0.0485389 8.28885 0.145424L9.24686 1.01261C9.39209 1.14402 9.59512 1.19095 9.78299 1.13566L11.0223 0.769448C11.2995 0.687662 11.5941 0.82975 11.7029 1.09757L12.1892 2.29386C12.2631 2.47547 12.4264 2.60602 12.6199 2.63761L13.8953 2.84562C14.1804 2.89227 14.3839 3.14712 14.366 3.43546L14.2849 4.7255C14.2727 4.92116 14.3636 5.10921 14.5242 5.2216L15.5828 5.96183C15.8195 6.12756 15.8921 6.44611 15.7508 6.69816L15.118 7.82511C15.0221 7.9961 15.022 8.20494 15.118 8.37589L15.7508 9.50187C15.8922 9.75385 15.8194 10.0724 15.5828 10.2382L14.5242 10.9794C14.3635 11.0918 14.2726 11.2798 14.2849 11.4755L14.366 12.7646C14.3841 13.053 14.1805 13.3087 13.8953 13.3554L12.6199 13.5634C12.4264 13.595 12.2631 13.7255 12.1892 13.9071L11.7029 15.1034C11.5941 15.3712 11.2995 15.5133 11.0223 15.4316L9.78299 15.0653C9.59523 15.0101 9.39209 15.0562 9.24686 15.1874L8.28885 16.0556C8.07468 16.2494 7.74823 16.2492 7.53397 16.0556L6.57596 15.1874C6.43066 15.056 6.22678 15.0099 6.03885 15.0653L4.79959 15.4316C4.52245 15.5131 4.22875 15.3711 4.11991 15.1034L3.63358 13.9071C3.55981 13.7256 3.39624 13.5951 3.20292 13.5634L1.92752 13.3554C1.64214 13.3089 1.4387 13.0531 1.45682 12.7646L1.53788 11.4755C1.55017 11.2799 1.45917 11.0919 1.29862 10.9794L0.240025 10.2382C0.00318809 10.0724 -0.0694757 9.75396 0.0720558 9.50187L0.703892 8.37589C0.799853 8.20492 0.79983 7.99609 0.703892 7.82511L0.0720558 6.69816C-0.0694351 6.44603 0.00311388 6.12756 0.240025 5.96183L1.29862 5.2216C1.45918 5.10921 1.55007 4.9211 1.53788 4.7255L1.45682 3.43546C1.43886 3.14701 1.64224 2.89213 1.92752 2.84562L3.20292 2.63761C3.39627 2.6059 3.5598 2.4754 3.63358 2.29386L4.11991 1.09757C4.22874 0.829845 4.52241 0.687805 4.79959 0.769448L6.03885 1.13566C6.2269 1.19112 6.43064 1.14423 6.57596 1.01261L7.53397 0.145424ZM7.91092 2.89738C5.03749 2.89758 2.7078 5.22702 2.7078 8.1005C2.70789 10.9739 5.03755 13.3034 7.91092 13.3036C10.7844 13.3035 13.114 10.974 13.114 8.1005C13.114 5.22697 10.7844 2.89749 7.91092 2.89738Z"
									fill="url(#paint1_linear_671_38030)" />
								<defs>
									<linearGradient id="paint0_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
									<linearGradient id="paint1_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
								</defs>
							</svg>
							<div class="d-flex flex-column">
								<span>Цвет никнейма</span>
								<small class="text-muted">{{ nicknameColor || 'Не установлен' }}</small>
							</div>
						</div>
					</div>
					<div class="d-flex align-items-center gap-2">
						<span v-if="nicknameColor" class="avatar-color-preview"
							:style="{ background: nicknameColor }"></span>
						<button class="btn theme-outline mt-0" data-bs-toggle="modal"
							data-bs-target="#nicknameColorModal">Выбрать</button>
					</div>
				</li>

				<!-- Анти-анлак система -->
				<li>
					<div class="profile-content">
						<div class="d-flex align-items-start gap-sm-2 gap-1">
							<svg class="mt-1" width="16" height="17" viewBox="0 0 16 17" fill="none"
								xmlns="http://www.w3.org/2000/svg">
								<path
									d="M7.66092 4.50382C7.76589 4.30154 8.05498 4.30154 8.15995 4.50382L9.16581 6.44035C9.20658 6.51891 9.28162 6.57425 9.36893 6.58878L11.5223 6.94621C11.7468 6.98369 11.8364 7.25854 11.6765 7.42081L10.1443 8.9755C10.0824 9.03857 10.054 9.12732 10.0672 9.21476L10.3924 11.373C10.426 11.5981 10.1918 11.7678 9.98807 11.6659L8.0369 10.6894C7.95776 10.6498 7.86411 10.6498 7.78495 10.6894L5.8328 11.6659C5.62912 11.7677 5.39577 11.5981 5.42948 11.373L5.75467 9.21476C5.76788 9.12719 5.7387 9.03859 5.67655 8.9755L4.1453 7.42081C3.98536 7.25848 4.07478 6.98352 4.29959 6.94621L6.45194 6.58878C6.5393 6.57428 6.61525 6.51895 6.65604 6.44035L7.66092 4.50382Z"
									fill="url(#paint0_linear_671_38030)" />
								<path fill-rule="evenodd" clip-rule="evenodd"
									d="M7.53397 0.145424C7.74827 -0.0484104 8.07463 -0.0485389 8.28885 0.145424L9.24686 1.01261C9.39209 1.14402 9.59512 1.19095 9.78299 1.13566L11.0223 0.769448C11.2995 0.687662 11.5941 0.82975 11.7029 1.09757L12.1892 2.29386C12.2631 2.47547 12.4264 2.60602 12.6199 2.63761L13.8953 2.84562C14.1804 2.89227 14.3839 3.14712 14.366 3.43546L14.2849 4.7255C14.2727 4.92116 14.3636 5.10921 14.5242 5.2216L15.5828 5.96183C15.8195 6.12756 15.8921 6.44611 15.7508 6.69816L15.118 7.82511C15.0221 7.9961 15.022 8.20494 15.118 8.37589L15.7508 9.50187C15.8922 9.75385 15.8194 10.0724 15.5828 10.2382L14.5242 10.9794C14.3635 11.0918 14.2726 11.2798 14.2849 11.4755L14.366 12.7646C14.3841 13.053 14.1805 13.3087 13.8953 13.3554L12.6199 13.5634C12.4264 13.595 12.2631 13.7255 12.1892 13.9071L11.7029 15.1034C11.5941 15.3712 11.2995 15.5133 11.0223 15.4316L9.78299 15.0653C9.59523 15.0101 9.39209 15.0562 9.24686 15.1874L8.28885 16.0556C8.07468 16.2494 7.74823 16.2492 7.53397 16.0556L6.57596 15.1874C6.43066 15.056 6.22678 15.0099 6.03885 15.0653L4.79959 15.4316C4.52245 15.5131 4.22875 15.3711 4.11991 15.1034L3.63358 13.9071C3.55981 13.7256 3.39624 13.5951 3.20292 13.5634L1.92752 13.3554C1.64214 13.3089 1.4387 13.0531 1.45682 12.7646L1.53788 11.4755C1.55017 11.2799 1.45917 11.0919 1.29862 10.9794L0.240025 10.2382C0.00318809 10.0724 -0.0694757 9.75396 0.0720558 9.50187L0.703892 8.37589C0.799853 8.20492 0.79983 7.99609 0.703892 7.82511L0.0720558 6.69816C-0.0694351 6.44603 0.00311388 6.12756 0.240025 5.96183L1.29862 5.2216C1.45918 5.10921 1.55007 4.9211 1.53788 4.7255L1.45682 3.43546C1.43886 3.14701 1.64224 2.89213 1.92752 2.84562L3.20292 2.63761C3.39627 2.6059 3.5598 2.4754 3.63358 2.29386L4.11991 1.09757C4.22874 0.829845 4.52241 0.687805 4.79959 0.769448L6.03885 1.13566C6.2269 1.19112 6.43064 1.14423 6.57596 1.01261L7.53397 0.145424ZM7.91092 2.89738C5.03749 2.89758 2.7078 5.22702 2.7078 8.1005C2.70789 10.9739 5.03755 13.3034 7.91092 13.3036C10.7844 13.3035 13.114 10.974 13.114 8.1005C13.114 5.22697 10.7844 2.89749 7.91092 2.89738Z"
									fill="url(#paint1_linear_671_38030)" />
								<defs>
									<linearGradient id="paint0_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
									<linearGradient id="paint1_linear_671_38030" x1="7.91136" y1="0" x2="7.91136"
										y2="16.2009" gradientUnits="userSpaceOnUse">
										<stop stop-color="#FF8C00" />
										<stop offset="1" stop-color="#FFD400" />
									</linearGradient>
								</defs>
							</svg>
							<div class="d-flex flex-column">
								<span>Анти-анлак система</span>
							</div>

						</div>
					</div>
					<input type="checkbox" :checked="isFeatureEnabled('anti_unluck')"
						@change="toggleFeature('anti_unluck', $event.target.checked)">
				</li>
			</ul>
		</div>

		<!-- Модалка выбора тарифа -->
		<div class="modal fade" id="purchasePremiumModal" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered modal-sm">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">PREMIUM</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<p class="text-muted mb-3">Выберите вариант подписки</p>
						<div class="d-flex flex-column gap-2 mb-3">
							<div v-for="plan in plans" :key="plan.id" class="form-check custom-radio"
								:class="{ 'opacity-50': plan.is_trial && !plan.trial_available }">
								<input class="form-check-input" type="radio" :value="plan.id" v-model="selectedPlanId"
									:id="'plan-' + plan.id" :disabled="plan.is_trial && !plan.trial_available">
								<label class="form-check-label d-flex align-items-center gap-2"
									:for="'plan-' + plan.id">

									<span>{{ plan.name }} за
										{{ plan.formatted_price }}</span>
									<span v-if="planDiscount(plan)" class="badge bg-warning">-{{ planDiscount(plan)
									}}%</span>
								</label>
							</div>
						</div>
						<button class="btn theme-btn w-100" @click="purchaseSelectedPlan"
							:disabled="!selectedPlanId || purchaseLoading">
							<span v-if="purchaseLoading">
								<span class="spinner-border spinner-border-sm me-1"></span>
							</span>
							<span v-else>Оплатить по СБП</span>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Модалка автопродления -->
		<div class="modal fade" id="autoRenewalModal" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered modal-sm">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Автопродление подписки</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<div class="d-flex align-items-center justify-content-between mb-3">
							<span>Включить автопродление</span>
							<input type="checkbox" v-model="autoRenewal" @change="toggleAutoRenewal">
						</div>
						<div class="d-flex align-items-center justify-content-between mb-3">
							<span>Отменить подписку</span>
							<button class="btn theme-outline px-1 py-0" data-bs-toggle="modal"
								data-bs-target="#cancelSubscriptionModal">Отменить</button>
						</div>

					</div>
				</div>
			</div>
		</div>

		<!-- Модалка подтверждения отмены подписки -->
		<div class="modal fade" id="cancelSubscriptionModal" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered modal-sm">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title text-center w-100">Вы уверены?</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<p class="text-muted mb-3 text-center">Оставшиеся дни подписки сгорят и подписка отменится</p>
						<div class="d-flex flex-column gap-2">
							<button class="btn theme-btn w-100" data-bs-dismiss="modal">Оставить подписку</button>
							<button class="btn theme-outline w-100" @click="cancelSubscription"
								:disabled="cancelLoading">
								<span v-if="cancelLoading" class="spinner-border spinner-border-sm me-1"></span>
								Отменить
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Модалка код-пароля -->
		<div class="modal fade" id="pinCodeModal" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered modal-sm">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="w-100 modal-title text-center">{{ pinMode === 'disable' ? 'Отключение код-пароля' :
							'Код пароль' }}</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<small class="text-muted mb-2 text-center d-block">
							{{ pinMode === 'disable' ? 'Введите текущий код - пароль' : 'Придумайте код пароль'
							}}</small>
						<div class="pin-dots d-flex gap-3 mb-3 justify-content-center">
							<span v-for="i in 4" :key="i" :class="{ active: pinCode.length >= i }"></span>
						</div>
						<div class="pin-keypad d-grid mb-3" style="grid-template-columns: repeat(3, 1fr); gap: 10px;">
							<button v-for="n in [1, 2, 3, 4, 5, 6, 7, 8, 9]" :key="n" class="pin-key"
								@click="pinEnter(n)" :disabled="pinCode.length >= 4">{{ n }}</button>
							<div class="pin-key empty"></div>
							<button class="pin-key" @click="pinEnter(0)" :disabled="pinCode.length >= 4">0</button>
							<button class="pin-key pin-key--delete" @click="pinDelete">
								<svg width="57" height="40" viewBox="0 0 57 40" fill="none"
									xmlns="http://www.w3.org/2000/svg">
									<path
										d="M24.8857 12.7717L32.5148 19.9049M40.1439 27.038L32.5148 19.9049M32.5148 19.9049L40.1439 12.7717M32.5148 19.9049L24.8857 27.038M54.148 2.5H16.525L3.14795 19.0489L16.525 37.5H54.148V2.5Z"
										stroke="#F2A93E" stroke-width="5" />
								</svg>
							</button>
						</div>
						<div v-if="pinMode !== 'disable'" class="mb-3">
							<label class="form-label small text-muted">Не запрашивать повторно в течение</label>
							<select class="form-select form-select-sm" v-model="pinCooldownSelect"
								@change="onCooldownSelectChange">
								<option value="0">Всегда спрашивать</option>
								<option value="5">5 минут</option>
								<option value="15">15 минут</option>
								<option value="30">30 минут</option>
								<option value="60">1 часа</option>
								<option value="custom">Произвольное</option>
							</select>
							<div v-if="pinCooldownSelect === 'custom'" class="input-group input-group-sm mt-2">
								<input type="number" class="form-control" v-model.number="pinCooldown" min="1"
									max="10080" placeholder="Кол-во">
								<span class="input-group-text">мин.</span>
							</div>
						</div>
						<button class="btn theme-btn w-100"
							@click="pinMode === 'disable' ? confirmDisablePinCode() : savePinCode()"
							:disabled="pinCode.length !== 4 || pinSaving">
							<span v-if="pinSaving">
								<span class="spinner-border spinner-border-sm me-1"></span>
							</span>
							<span v-else>{{ pinMode === 'disable' ? 'Отключить' : 'Подтвердить' }}</span>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Модалка выбора цвета обводки аватарки -->
		<div class="modal fade" id="avatarColorModal" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered modal-sm">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Цвет обводки</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<small class="text-muted mb-3">Выберите цвет для обводки аватарки</small>
						<div class="color-picker-wrap">
							<ColorPicker is-widget picker-type="chrome" v-model:pureColor="avatarColorPick" format="hex"
								:disableAlpha="true" :disableHistory="true" />
						</div>
						<button class="btn theme-btn w-100 mt-3" @click="saveAvatarBorderColor"
							:disabled="avatarColorSaving">
							<span v-if="avatarColorSaving">
								<span class="spinner-border spinner-border-sm me-1"></span>
							</span>
							<span v-else>Подтвердить</span>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Модалка выбора цвета никнейма -->
		<div class="modal fade" id="nicknameColorModal" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered modal-sm">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Цвет никнейма</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<small class="text-muted mb-3">Выберите цвет для никнейма</small>
						<div class="color-picker-wrap">
							<ColorPicker is-widget picker-type="chrome" v-model:pureColor="nicknameColorPick"
								format="hex" :disableAlpha="true" :disableHistory="true" />
						</div>
						<button class="btn theme-btn w-100 mt-3" @click="saveNicknameColor"
							:disabled="nicknameColorSaving">
							<span v-if="nicknameColorSaving">
								<span class="spinner-border spinner-border-sm me-1"></span>
							</span>
							<span v-else>Подтвердить</span>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Модалка успешной установки код-пароля -->
		<div class="modal fade" id="pinCodeSuccessModal" tabindex="-1">
			<div class="modal-dialog modal-dialog-centered modal-sm">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Код-пароль установлен</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body text-center">
						<div class="pin-success-icon mb-3">
							<svg width="48" height="48" viewBox="0 0 24 24" fill="none"
								xmlns="http://www.w3.org/2000/svg">
								<path
									d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z"
									stroke="#4CAF50" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
							</svg>
						</div>
						<p class="text-muted mb-2">Ваш код-пароль:</p>
						<div class="pin-code-display mb-3">
							<span v-for="(digit, i) in savedPinCode.split('')" :key="i" class="pin-digit">{{ digit
							}}</span>
						</div>
						<small class="text-muted">Запомните его.
							{{ pinCooldown > 0 ? 'Код не будет запрашиваться повторно в течение '
								+ pinCooldown + ' минут после ввода.' : 'Код будет запрашиваться при каждом входе.'
							}}</small>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn theme-btn w-100" data-bs-dismiss="modal">Понятно</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Модалка оплаты -->
		<div v-if="paymentUrl" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.7)">
			<div class="modal-dialog modal-lg modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Оплата подписки</h5>
						<button type="button" class="btn-close" @click="closePayment"></button>
					</div>
					<div class="modal-body p-0">
						<iframe :src="paymentUrl" style="width: 100%; height: 500px; border: none;"></iframe>
					</div>
					<div class="modal-footer justify-content-between">
						<p class="text-muted small mb-0">
							<span class="spinner-border spinner-border-sm me-1" v-if="checkingPayment"></span>
							Ожидаем подтверждение оплаты...
						</p>
						<button class="btn theme-btn" @click="forceCheckPayment" :disabled="checkingPayment">
							Я оплатил
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { ColorPicker } from 'vue3-colorpicker';
import 'vue3-colorpicker/style.css';

export default {
	components: { ColorPicker },
	name: 'ProfilePremium',
	props: {
		client: { type: Object, required: true }
	},
	data() {
		return {
			loading: true,
			subscription: { is_active: false },
			plans: [],
			purchaseLoading: false,
			selectedPlanId: null,
			paymentUrl: null,
			paymentId: null,
			checkingPayment: false,
			pollingInterval: null,
			features: [],
			pinCode: '',
			pinSaving: false,
			hasPinCode: false,
			savedPinCode: '',
			avatarBorderColor: null,
			avatarColorPick: '#ff0000',
			avatarColorSaving: false,
			nicknameColor: null,
			nicknameColorPick: '#ff0000',
			nicknameColorSaving: false,
			pinCooldown: 0,
			pinCooldownSelect: '0',
			pinCooldownSaving: false,
			pinMode: 'set',
			autoRenewal: false,
			cancelLoading: false
		};
	},
	computed: {
		ps() {
			return this.subscription.premium_settings || {};
		},
	},
	methods: {
		planDiscount(plan) {
			const weekPlan = this.plans.find(p => p.duration_days === 7);
			if (!weekPlan || plan.id === weekPlan.id || plan.is_trial) return 0;
			const weekDailyPrice = weekPlan.price / weekPlan.duration_days;
			const planDailyPrice = plan.price / plan.duration_days;
			const discount = Math.floor((1 - planDailyPrice / weekDailyPrice) * 100);
			return discount > 0 ? discount : 0;
		},

		async loadData() {
			this.loading = true;
			try {
				const [statusRes, plansRes] = await Promise.all([
					fetch('/api/subscription/status'),
					fetch('/api/subscription/plans')
				]);
				const statusData = await statusRes.json();
				const plansData = await plansRes.json();

				if (statusData.success) {
					this.subscription = statusData.data;
					this.hasPinCode = !!statusData.data.has_pin_code;
					this.pinCooldown = statusData.data.settings?.pin_code_cooldown ?? 0;
					const presets = ['0', '5', '15', '30', '60'];
					this.pinCooldownSelect = presets.includes(String(this.pinCooldown)) ? String(this.pinCooldown) : 'custom';
					this.avatarBorderColor = statusData.data.avatar_border_color || null;
					if (this.avatarBorderColor) this.avatarColorPick = this.avatarBorderColor;
					this.nicknameColor = statusData.data.nickname_color || null;
					if (this.nicknameColor) this.nicknameColorPick = this.nicknameColor;
					this.autoRenewal = !!statusData.data.auto_renewal;
				}
				if (plansData.success) {
					this.plans = plansData.data;
					const firstAvailable = this.plans.find(p => !(p.is_trial && !p.trial_available));
					if (firstAvailable) this.selectedPlanId = firstAvailable.id;
				}

				this.buildFeatures();
			} catch (e) {
				console.error('Ошибка загрузки данных подписки', e);
			} finally {
				this.loading = false;
			}
		},

		buildFeatures() {
			const s = this.ps;
			this.features = [
				{ key: 'case_discount', title: 'Скидка на кейсы', description: `-${s.case_discount_low || 10}% на кейсы до ${s.case_discount_threshold || 500}₽, -${s.case_discount_high || 5}% на дорогие` },
				{ key: 'marketplace_fee', title: 'Скидка на комиссию продаж', description: `Комиссия маркетплейса ${s.marketplace_fee || 6}% вместо ${s.marketplace_fee_default || 5}%` },
				{ key: 'avatar_border', title: 'Обводка аватарки', description: 'Премиальная рамка вокруг аватарки' },
				{ key: 'anti_unluck', title: 'Анти-анлак система', description: '10 неокупов подряд — следующий кейс бесплатно' }
			];
		},

		purchaseSelectedPlan() {
			const plan = this.plans.find(p => p.id === this.selectedPlanId);
			if (plan) this.purchasePlan(plan);
		},

		async purchasePlan(plan) {
			this.purchaseLoading = true;
			this.selectedPlanId = plan.id;
			try {
				const res = await fetch('/api/subscription/purchase', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
					body: JSON.stringify({ plan_id: plan.id })
				});
				const data = await res.json();
				if (data.success && data.test_payment) {
					// Тестовый платёж — подписка уже активирована
					const modal = bootstrap.Modal.getInstance(document.getElementById('purchasePremiumModal'));
					if (modal) modal.hide();
					await this.loadData();
				} else if (data.success) {
					this.paymentUrl = data.payment_form_url;
					this.paymentId = data.payment_id;
					this.startPolling();
				} else {
					alert(data.message || 'Ошибка создания платежа');
				}
			} catch (e) {
				alert('Ошибка создания платежа');
			} finally {
				this.purchaseLoading = false;
				this.selectedPlanId = null;
			}
		},

		startPolling() {
			this.pollingInterval = setInterval(() => this.checkPaymentStatus(), 7000);
		},

		stopPolling() {
			if (this.pollingInterval) {
				clearInterval(this.pollingInterval);
				this.pollingInterval = null;
			}
		},

		async checkPaymentStatus() {
			if (!this.paymentId) return;
			try {
				const res = await fetch(`/api/subscription/payment-status/${this.paymentId}`);
				const data = await res.json();
				if (data.success && data.data.is_paid) {
					this.stopPolling();
					this.paymentUrl = null;
					this.paymentId = null;
					await this.loadData();
				}
			} catch (e) {
				console.error('Ошибка проверки статуса', e);
			}
		},

		async forceCheckPayment() {
			this.checkingPayment = true;
			await this.checkPaymentStatus();
			this.checkingPayment = false;
		},

		closePayment() {
			this.stopPolling();
			this.paymentUrl = null;
			this.paymentId = null;
		},

		async toggleFeature(feature, enabled, pinCode = null) {
			// При выключении код-пароля — запросить ввод старого кода
			if (feature === 'pin_code' && !enabled && this.hasPinCode && !pinCode) {
				this.pinMode = 'disable';
				this.pinCode = '';
				const modal = new bootstrap.Modal(document.getElementById('pinCodeModal'));
				modal.show();
				return;
			}

			const body = { feature, enabled };
			if (pinCode) body.pin_code = pinCode;

			try {
				const res = await fetch('/api/subscription/toggle-feature', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
					body: JSON.stringify(body)
				});
				const data = await res.json();
				if (data.success) {
					this.subscription.settings = data.settings;
				} else {
					if (window.toast) window.toast.error(data.message || 'Ошибка');
				}
			} catch (e) {
				console.error('Ошибка переключения функции', e);
			}
		},

		async toggleAutoRenewal() {
			try {
				const res = await fetch('/api/subscription/toggle-auto-renewal', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
					body: JSON.stringify({ enabled: this.autoRenewal })
				});
				const data = await res.json();
				if (data.success) {
					if (window.toast) window.toast.success(this.autoRenewal ? 'Автопродление включено' : 'Автопродление отключено');
				} else {
					this.autoRenewal = !this.autoRenewal;
					if (window.toast) window.toast.error(data.message || 'Ошибка');
				}
			} catch (e) {
				this.autoRenewal = !this.autoRenewal;
				if (window.toast) window.toast.error('Ошибка сохранения');
			}
		},

		async cancelSubscription() {
			this.cancelLoading = true;
			try {
				const res = await fetch('/api/subscription/cancel', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
				});
				const data = await res.json();
				if (data.success) {
					const modal = bootstrap.Modal.getInstance(document.getElementById('cancelSubscriptionModal'));
					if (modal) modal.hide();
					const autoModal = bootstrap.Modal.getInstance(document.getElementById('autoRenewalModal'));
					if (autoModal) autoModal.hide();
					if (window.toast) window.toast.success('Подписка отменена');
					await this.loadData();
				} else {
					if (window.toast) window.toast.error(data.message || 'Ошибка отмены подписки');
				}
			} catch (e) {
				if (window.toast) window.toast.error('Ошибка отмены подписки');
			} finally {
				this.cancelLoading = false;
			}
		},

		pinEnter(n) {
			if (this.pinCode.length < 4) this.pinCode += String(n);
		},

		pinDelete() {
			this.pinCode = this.pinCode.slice(0, -1);
		},

		async savePinCode() {
			if (this.pinCode.length !== 4) return;
			this.pinSaving = true;
			try {
				const res = await fetch('/api/subscription/pin-code', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
					body: JSON.stringify({ pin_code: this.pinCode, cooldown: this.pinCooldown })
				});
				const data = await res.json();
				if (data.success) {
					this.hasPinCode = true;
					this.savedPinCode = data.pin_code;
					const pinModal = bootstrap.Modal.getInstance(document.getElementById('pinCodeModal'));
					if (pinModal) pinModal.hide();
					this.$nextTick(() => {
						const successModal = new bootstrap.Modal(document.getElementById('pinCodeSuccessModal'));
						successModal.show();
					});
					this.pinCode = '';
				} else {
					if (window.toast) window.toast.error(data.message || 'Ошибка сохранения кода');
					this.pinCode = '';
				}
			} catch (e) {
				if (window.toast) window.toast.error('Ошибка сохранения кода');
				this.pinCode = '';
			} finally {
				this.pinSaving = false;
			}
		},

		async confirmDisablePinCode() {
			if (this.pinCode.length !== 4) return;
			this.pinSaving = true;
			try {
				await this.toggleFeature('pin_code', false, this.pinCode);
				if (!this.isFeatureEnabled('pin_code')) {
					this.pinCode = '';
					this.pinMode = 'set';
					const modal = bootstrap.Modal.getInstance(document.getElementById('pinCodeModal'));
					if (modal) modal.hide();
				} else {
					this.pinCode = '';
				}
			} catch (e) {
				if (window.toast) window.toast.error('Ошибка отключения код-пароля');
				this.pinCode = '';
			} finally {
				this.pinSaving = false;
			}
		},

		onCooldownSelectChange() {
			if (this.pinCooldownSelect === 'custom') {
				this.pinCooldown = 1;
			} else {
				this.pinCooldown = parseInt(this.pinCooldownSelect);
			}
		},

		async setPinCooldown(minutes) {
			if (minutes === null || minutes === undefined || minutes === '') return;
			minutes = parseInt(minutes);
			if (isNaN(minutes) || minutes < 0) return;
			this.pinCooldownSaving = true;
			try {
				const res = await fetch('/api/subscription/pin-code-cooldown', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
					},
					body: JSON.stringify({ cooldown: minutes })
				});
				const data = await res.json();
				if (data.success) {
					this.pinCooldown = data.cooldown;
					this.pinCooldownCustom = null;
					if (window.toast) window.toast.success('Кулдаун сохранён');
				} else {
					if (window.toast) window.toast.error(data.message || 'Ошибка');
				}
			} catch (e) {
				if (window.toast) window.toast.error('Ошибка сохранения');
			} finally {
				this.pinCooldownSaving = false;
			}
		},

		async saveAvatarBorderColor() {
			this.avatarColorSaving = true;
			try {
				const color = typeof this.avatarColorPick === 'string' ? this.avatarColorPick : '#ff0000';
				const res = await fetch('/api/subscription/avatar-border-color', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
					body: JSON.stringify({ color })
				});
				const data = await res.json();
				if (data.success) {
					this.avatarBorderColor = data.color;
					this.$emit('update-client', { avatar_border_color: data.color });
					const modal = bootstrap.Modal.getInstance(document.getElementById('avatarColorModal'));
					if (modal) modal.hide();
					if (window.toast) window.toast.success('Цвет обводки сохранён');
				} else {
					if (window.toast) window.toast.error(data.message || 'Ошибка сохранения');
				}
			} catch (e) {
				if (window.toast) window.toast.error('Ошибка сохранения');
			} finally {
				this.avatarColorSaving = false;
			}
		},

		async saveNicknameColor() {
			this.nicknameColorSaving = true;
			try {
				const color = typeof this.nicknameColorPick === 'string' ? this.nicknameColorPick : '#ff0000';
				const res = await fetch('/api/subscription/nickname-color', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
					body: JSON.stringify({ color })
				});
				const data = await res.json();
				if (data.success) {
					this.nicknameColor = data.color;
					this.$emit('update-client', { nickname_color: data.color });
					const modal = bootstrap.Modal.getInstance(document.getElementById('nicknameColorModal'));
					if (modal) modal.hide();
					if (window.toast) window.toast.success('Цвет никнейма сохранён');
				} else {
					if (window.toast) window.toast.error(data.message || 'Ошибка сохранения');
				}
			} catch (e) {
				if (window.toast) window.toast.error('Ошибка сохранения');
			} finally {
				this.nicknameColorSaving = false;
			}
		},

		isFeatureEnabled(key) {
			if (!this.subscription.settings) return true;
			return this.subscription.settings[key] ?? true;
		},

		formatDate(isoString) {
			if (!isoString) return '';
			const d = new Date(isoString);
			return d.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
		}
	},
	mounted() {
		this.loadData();
		document.getElementById('pinCodeModal')?.addEventListener('show.bs.modal', () => {
			this.pinCode = '';
		});
	},
	beforeUnmount() {
		this.stopPolling();
	}
};
</script>
