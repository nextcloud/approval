<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<NcLoadingIcon v-if="state === null" />
		<Info v-else
			:state="state"
			:timestamp="timestamp"
			:message="message"
			:user-name="userName"
			:user-id="userId"
			:rule="rule"
			:user-rules="userRules"
			@approve="onApprove"
			@reject="onReject"
			@request="showRequestModal = true" />
		<RequestModal v-if="showRequestModal"
			:user-rules="userRules"
			@request="onRequestSubmit"
			@close="showRequestModal = false" />
	</div>
</template>

<script>
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import Info from '../components/Info.vue'
import RequestModal from '../components/RequestModal.vue'

import { getApprovalState, getUserRequesterRules, requestApproval, approve, reject } from '../files/helpers.js'

export default {
	name: 'ApprovalTab',

	components: {
		RequestModal,
		Info,
		NcLoadingIcon,
	},

	props: {
	},

	data() {
		return {
			fileInfo: null,
			state: null,
			timestamp: null,
			userName: null,
			userId: null,
			rule: null,
			message: '',
			userRules: [],
			showRequestModal: false,
		}
	},

	computed: {
	},

	watch: {
	},

	beforeMount() {
	},

	mounted() {
	},

	methods: {
		update(fileInfo) {
			console.debug('[Approval] sidebar tab update', fileInfo)
			this.fileInfo = fileInfo
			this.state = null
			getApprovalState(fileInfo.id).then(response => {
				this.state = response.data.ocs.data.state
				this.timestamp = response.data.ocs.data.timestamp
				this.message = response.data.ocs.data.message
				this.userId = response.data.ocs.data.userId
				this.userName = response.data.ocs.data.userName
				this.rule = response.data.ocs.data.rule
			}).catch(error => {
				console.error(error)
			})
			this.updateUserRules(fileInfo.id)
		},
		updateUserRules(fileId) {
			getUserRequesterRules(fileId).then(response => {
				this.userRules = response.data.ocs.data
			})
		},
		async onApprove(message) {
			this.state = null
			const fileId = this.fileInfo.id
			const fileName = this.fileInfo.name
			await approve(fileId, fileName, null, true, message)
			this.update(this.fileInfo)
		},
		async onReject(message) {
			this.state = null
			const fileId = this.fileInfo.id
			const fileName = this.fileInfo.name
			await reject(fileId, fileName, null, true, message)
			this.update(this.fileInfo)
		},
		async onRequestSubmit(ruleId, createShares) {
			this.showRequestModal = false
			await requestApproval(this.fileInfo.id, this.fileInfo.name, ruleId, createShares)
			this.update(this.fileInfo)
		},
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
