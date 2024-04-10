<template>
	<div>
		<NcLoadingIcon v-if="state === null" />
		<Info v-else
			:state="state"
			:timestamp="timestamp"
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
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import Info from '../components/Info.vue'
import RequestModal from '../components/RequestModal.vue'

import { getApprovalState, getUserRequesterRules, requestApproval } from '../files/helpers.js'

import { generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

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
			userRules: [],
			showRequestModal: false,
		}
	},

	computed: {
	},

	watch: {
	},

	beforeDestroy() {
	},

	beforeMount() {
	},

	mounted() {
	},

	methods: {
		update(fileInfo) {
			this.fileInfo = fileInfo
			this.state = null
			getApprovalState(fileInfo.id).then(response => {
				this.state = response.data.ocs.data.state
				this.timestamp = response.data.ocs.data.timestamp
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
		async onApprove() {
			this.state = null
			const fileId = this.fileInfo.id
			const fileName = this.fileInfo.name
			const url = generateOcsUrl('apps/approval/api/v1/approve/{fileId}', { fileId })
			try {
				await axios.put(url)
				showSuccess(t('approval', 'You approved {name}', { name: fileName }))
				// await updateNodeApprovalState(node)
				this.update(this.fileInfo)
			} catch (error) {
				console.error(error)
				showError(t('approval', 'Failed to approve {name}', { name: fileName }))
				throw error
			}
		},
		async onReject() {
			this.state = null
			const fileId = this.fileInfo.id
			const fileName = this.fileInfo.name
			const url = generateOcsUrl('apps/approval/api/v1/reject/{fileId}', { fileId })
			try {
				await axios.put(url)
				showSuccess(t('approval', 'You rejected {name}', { name: fileName }))
				// await updateNodeApprovalState(node)
				this.update(this.fileInfo)
			} catch (error) {
				console.error(error)
				showError(t('approval', 'Failed to reject {name}', { name: fileName }))
				throw error
			}
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
