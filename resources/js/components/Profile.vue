<template>
	<ProfileLayout 
		:initial-client="initialClient"
		:telegram-bot-name="telegramBotName" />
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
		}
	}
}
</script>