<template>
	<ProfileLayout
		:initial-client="initialClient"
		:telegram-bot-name="telegramBotName"
		:deposit-settings="depositSettings"
		:withdraw-settings="withdrawSettings" />
</template>

<script>
import ProfileLayout from './profile/Layout.vue';

export default {
	name: 'Profile',
	components: {
		ProfileLayout
	},
	props: {
		initialClient: {
			type: Object,
			required: true
		},
		telegramBotName: {
			type: String,
			default: ''
		},
		depositSettings: {
			type: Object,
			default: () => ({
				minimum_amount: 100,
				maximum_amount: 50000,
				enabled: true
			})
		},
		withdrawSettings: {
			type: Object,
			default: () => ({ minimum_amount: 100 })
		}
	}
}
</script>