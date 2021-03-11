<template>
	<div>
		<button @click="onYes">
			{{ yesText }}
		</button>
		<button @click="onNo">
			{{ noText }}
		</button>
	</div>
</template>

<script>

export default {
	name: 'ApprovalButtons',

	components: {
	},

	props: {
		yesText: {
			type: String,
			default: t('approval', 'Approve'),
		},
		noText: {
			type: String,
			default: t('approval', 'Refuse'),
		},
	},
	data() {
		return {
		}
	},

	computed: {
	},

	mounted() {
	},

	methods: {
		onYes() {
			this.$emit('yes')
			const event = new CustomEvent('yes', {})
			document.dispatchEvent(event)
		},
		onNo() {
			this.$emit('no')
		},
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
