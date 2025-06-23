<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcModal v-if="show"
		size="normal"
		:name="t('approval', 'Request approval')"
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
import NcModal from '@nextcloud/vue/components/NcModal'

import RequestForm from './RequestForm.vue'

export default {
	name: 'RequestModal',

	components: {
		RequestForm,
		NcModal,
	},

	props: {
		userRules: {
			type: Array,
			required: true,
		},
	},

	emits: ['request', 'close'],

	data() {
		return {
			show: true,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		closeModal() {
			this.show = false
			this.$emit('close')
		},
		onRequest(ruleId, createShares) {
			this.closeModal()
			this.$emit('request', ruleId, createShares)
		},
	},
}
</script>

<style scoped lang="scss">
.request-modal-content {
	padding: 16px;
}
</style>
