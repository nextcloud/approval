<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcModal
		v-if="show"
		size="normal"
		:name="t('approval', 'Approval information')"
		@close="closeModal">
		<Info
			:state="state"
			:timestamp="timestamp"
			:user-name="userName"
			:user-id="userId"
			:rule="rule"
			:user-rules="userRules"
			@approve="onApprove"
			@reject="onReject"
			@request="onRequest" />
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'

import Info from './Info.vue'

export default {
	name: 'InfoModal',

	components: {
		Info,
		NcModal,
	},

	props: {},

	emits: ['approve', 'reject', 'request', 'close'],

	data() {
		return {
			show: false,
			node: null,
			userRules: [],
		}
	},

	computed: {
		state() {
			return this.node.attributes['approval-state']
		},
		timestamp() {
			return this.node.attributes['approval-timestamp']
		},
		userName() {
			return this.node.attributes['approval-userName']
		},
		userId() {
			return this.node.attributes['approval-userId']
		},
		rule() {
			return this.node.attributes['approval-rule']
		},
	},

	watch: {},

	mounted() {},

	methods: {
		showModal() {
			this.show = true
		},
		closeModal() {
			this.show = false
			this.$emit('close')
		},
		setNode(node) {
			this.node = node
		},
		setUserRules(rules) {
			this.userRules = rules
		},
		onApprove(message) {
			this.closeModal()
			this.$emit('approve', this.node, message)
		},
		onReject(message) {
			this.closeModal()
			this.$emit('reject', this.node, message)
		},
		onRequest() {
			this.closeModal()
			this.$emit('request', this.node)
		},
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
