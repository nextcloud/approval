<template>
	<NcModal v-if="show"
		size="normal"
		@close="closeModal">
		<div class="request-modal-content">
			<RequestForm :rules="userRules"
				class="info-modal"
				@request="onRequest"
				@cancel="closeModal" />
		</div>
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import RequestForm from './RequestForm.vue'

export default {
	name: 'RequestModal',

	components: {
		RequestForm,
		NcModal,
	},

	props: {
	},

	data() {
		return {
			show: false,
			userRules: [],
			node: null,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		showModal() {
			this.show = true
		},
		closeModal() {
			this.show = false
			this.$emit('close')
			// this.reset()
		},
		setUserRules(rules) {
			this.userRules = rules
		},
		setNode(node) {
			this.node = node
		},
		onRequest(ruleId, createShares) {
			this.closeModal()
			this.$emit('request', this.node, ruleId, createShares)
			// this.requesting = true
		},
	},
}
</script>

<style scoped lang="scss">
.request-modal-content {
	padding: 16px;
}
</style>
