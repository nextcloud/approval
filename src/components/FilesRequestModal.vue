<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<RequestModal v-if="show"
		:user-rules="userRules"
		@request="onRequest"
		@close="closeModal" />
</template>

<script>
import RequestModal from './RequestModal.vue'

export default {
	name: 'FilesRequestModal',

	components: {
		RequestModal,
	},

	props: {
	},

	emits: ['request', 'close'],

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
		},
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
