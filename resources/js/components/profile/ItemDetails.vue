<template>
	<div class="item-details sticky-top" v-if="item">
		<h5 class="item-name">{{ getItemName(item) }}</h5>
		<div class="item-type text-muted mb-3">{{ getItemType(item) }}</div>
		
		<!-- Изображение предмета -->
		<div class="item-preview mb-3">
			<img :src="getIconUrl(item)" :alt="item.market_hash_name" 
				 class="img-fluid" @error="handleImageError">
		</div>
		
		<!-- Описание предмета -->
		<div v-if="getItemDescription(item)" class="item-description mb-3">
			<div class="description-text text-muted" v-html="getItemDescription(item)"></div>
		</div>
		
		<!-- Стикеры -->
		<div v-if="item.parsed_stickers && item.parsed_stickers.length > 0" class="item-stickers mb-3">
			<strong>Стикеры:</strong>
			<div class="sticker-list mt-2">
				<div v-for="(sticker, index) in item.parsed_stickers" :key="index" class="sticker-item">
					<img v-if="sticker.img" :src="sticker.img" :alt="sticker.name" class="sticker-img">
					<span>{{ sticker.name }}</span>
				</div>
			</div>
		</div>
		
		<!-- Теги с Float и Паттерном -->
		<div class="item-tags mb-3">
			<strong>Информация о предмете:</strong>
			<div class="tags-list mt-2">
				<!-- Остальные теги -->
				<div v-if="item.structured_tags && item.structured_tags.length > 0" 
				     v-for="tag in item.structured_tags" :key="tag.id" 
				     class="tag-item d-flex justify-content-between mb-1">
					<span class="tag-category text-muted text-nowrap me-3">{{ tag.category_name }}:</span>
					<span class="tag-name text-nowrap text-truncate" :title="tag.display_name">
						{{ tag.display_name }}
					</span>
				</div>
				<!-- Float значение -->
				<div v-if="item.float_value" class="tag-item d-flex justify-content-between mb-1">
					<span class="tag-category text-muted">Износ:</span>
					<span class="tag-name">{{ parseFloat(item.float_value).toFixed(6) }}</span>
				</div>
				<!-- Паттерн -->
				<div v-if="item.pattern_index" class="tag-item d-flex justify-content-between mb-1">
					<span class="tag-category text-muted">Паттерн:</span>
					<span class="tag-name">#{{ item.pattern_index }}</span>
				</div>
			</div>
		</div>
		
		<!-- Кнопки действий -->
		<div class="item-actions mt-4">
			<slot name="actions" :item="item">
				<!-- Кнопки по умолчанию для инвентаря -->
				<div v-if="activeTab === 'available'">
					<button v-if="item.tradable && item.marketable && hasTradeUrl && !item.is_listed" 
						class="btn theme-btn w-100 mb-2"
						:disabled="isCreatingListing"
						@click="$emit('sell', item)">
						<i v-if="isCreatingListing" class="ri-loader-4-line me-2 ri-spin"></i>
						<i v-else class="ri-price-tag-3-line me-2"></i>
						{{ isCreatingListing ? 'Создаем листинг...' : 'Продать' }}
					</button>
					<div v-else-if="!hasTradeUrl" class="alert alert-light mb-0 small">
						<i class="ri-information-line me-2"></i>Для того чтобы выставить на продажу нужно добавить Trade URL в настройках <a href="/profile#profile">профиля</a>
					</div>
					<div v-else-if="!item.tradable || !item.marketable" class="alert alert-secondary mb-0">
						<i class="ri-lock-line me-2"></i>Данный предмет нельзя продать
					</div>
				</div>
				<div v-if="activeTab === 'listed'">
					<div class="alert alert-info mb-0">
						<i class="ri-information-line me-2"></i>Этот предмет выставлен на продажу
					</div>
				</div>
			</slot>
		</div>
	</div>
</template>

<script>
export default {
	name: 'ItemDetails',
	props: {
		item: {
			type: Object,
			default: null
		},
		activeTab: {
			type: String,
			default: 'available'
		},
		hasTradeUrl: {
			type: Boolean,
			default: false
		},
		isCreatingListing: {
			type: Boolean,
			default: false
		}
	},
	emits: ['sell'],
	methods: {
		getItemName(item) {
			return item?.item?.name_ru || item?.market_hash_name || '';
		},
		
		getItemType(item) {
			if (item?.structured_tags && item.structured_tags.length > 0) {
				const typeTag = item.structured_tags.find(tag => tag.category_code === 'type');
				return typeTag ? typeTag.display_name : 'Предмет';
			}
			return 'Предмет';
		},
		
		getIconUrl(item) {
			// Для листингов используем inventory_icon_url, для предметов инвентаря - icon_url
			const iconUrl = item?.inventory_icon_url || item?.icon_url;
			
			if (iconUrl) {
				// Проверяем, уже ли это полный URL
				if (iconUrl.startsWith('http')) {
					return iconUrl;
				}
				// Если нет, добавляем префикс Steam
				return 'https://community.steamstatic.com/economy/image/' + iconUrl;
			}
			if (item?.image_url) {
				return item.image_url;
			}
			return '/images/skin_no_image.svg';
		},
		
		handleImageError(event) {
			event.target.src = '/images/skin_no_image.svg';
		},
		
		getParsedDescriptions(item) {
			if (!item?.descriptions) return [];
			if (typeof item.descriptions === 'string') {
				try {
					return JSON.parse(item.descriptions);
				} catch (e) {
					return [];
				}
			}
			return item.descriptions;
		},
		
		getItemDescription(item) {
			const descriptions = this.getParsedDescriptions(item);
			const descriptionItem = descriptions.find(desc => desc.name === 'description');
			return descriptionItem ? descriptionItem.value : null;
		}
	}
}
</script>