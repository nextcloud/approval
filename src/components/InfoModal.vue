<template>
	<NcModal v-if="show"
		size="normal"
		@close="closeModal">
		<div class="info-modal-content">
			<ApprovalButtons v-if="stateApprovable"
				class="buttons"
				:approve-text="approveText"
				:reject-text="rejectText"
				@approve="onApprove"
				@reject="onReject" />
			<span v-if="stateApproved"
				class="state-label approved-label">
				<CheckCircleIcon class="approved" :size="32" />
				<span v-if="userId && timestamp"
					class="details">
					<strong>{{ approvedByText }}</strong>
					<NcUserBubble
						class="user-bubble"
						:user="userId"
						:display-name="notMe ? userName : you"
						:size="24" />
					{{ relativeTime }}
				</span>
				<span v-else>{{ approvedText }}</span>
			</span>
			<span v-if="stateRejected"
				class="state-label rejected-label">
				<CloseCircleIcon class="rejected" :size="32" />
				<span v-if="userId && timestamp"
					class="details">
					<strong>{{ rejectedByText }}</strong>
					<NcUserBubble
						class="user-bubble"
						:user="userId"
						:display-name="notMe ? userName : you"
						:size="24" />
					{{ relativeTime }}
				</span>
				<span v-else>{{ rejectedText }}</span>
			</span>
			<span v-if="statePending"
				class="state-label pending-label">
				<CheckCircleIcon class="pending" :size="32" />
				<span v-if="userId && timestamp"
					class="details">
					<strong>{{ requestedByText }}</strong>
					<NcUserBubble
						class="user-bubble"
						:user="userId"
						:display-name="notMe ? userName : you"
						:size="24" />
					{{ relativeTime }}
				</span>
				<span v-else>{{ pendingTextWithTime }}</span>
			</span>
			<NcButton v-if="canRequestApproval"
				@click="onRequest">
				<template #icon>
					<CheckIcon :size="20" />
				</template>
				{{ t('approval', 'Request approval') }}
			</NcButton>
			<div class="info">
				<span>{{ infoText }}</span>
				<NcUserBubble v-if="stateApprovable && userName"
					class="user-bubble"
					:user="userId"
					:display-name="notMe ? userName : you"
					:size="24" />
				<span>{{ ruleText }}</span>
			</div>
		</div>
	</NcModal>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CheckCircleIcon from 'vue-material-design-icons/CheckCircle.vue'
import CloseCircleIcon from 'vue-material-design-icons/CloseCircle.vue'

import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcUserBubble from '@nextcloud/vue/dist/Components/NcUserBubble.js'

import ApprovalButtons from './ApprovalButtons.vue'

import { states } from '../states.js'

import { getCurrentUser } from '@nextcloud/auth'
import moment from '@nextcloud/moment'

export default {
	name: 'InfoModal',

	components: {
		ApprovalButtons,
		NcModal,
		NcButton,
		NcUserBubble,
		CheckCircleIcon,
		CloseCircleIcon,
		CheckIcon,
	},

	props: {
		approveText: {
			type: String,
			default: t('approval', 'Approve'),
		},
		rejectText: {
			type: String,
			default: t('approval', 'Reject'),
		},
		approvedText: {
			type: String,
			default: t('approval', 'Approved'),
		},
		approvedByText: {
			type: String,
			default: t('approval', 'Approved by'),
		},
		rejectedText: {
			type: String,
			default: t('approval', 'Rejected'),
		},
		rejectedByText: {
			type: String,
			default: t('approval', 'Rejected by'),
		},
		requestedByText: {
			type: String,
			default: t('approval', 'Approval requested by'),
		},
		pendingText: {
			type: String,
			default: t('approval', 'Approval requested'),
		},
	},

	data() {
		return {
			you: t('approval', 'you'),
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
		pendingTextWithTime() {
			return this.timestamp
				? this.pendingText + ' ' + this.relativeTime
				: this.pendingText
		},
		stateApproved() {
			return this.state === states.APPROVED
		},
		stateRejected() {
			return this.state === states.REJECTED
		},
		statePending() {
			return this.state === states.PENDING
		},
		stateApprovable() {
			return this.state === states.APPROVABLE
		},
		relativeTime() {
			return moment.unix(this.timestamp).fromNow()
		},
		notMe() {
			return this.userId !== getCurrentUser().uid
		},
		canRequestApproval() {
			return this.userRules.length > 0
		},
		ruleText() {
			if ([states.APPROVED, states.PENDING, states.REJECTED, states.APPROVABLE].includes(this.state)) {
				return t('approval', 'The related approval workflow is: {ruleDescription}', { ruleDescription: this.rule.description })
			}
			return ''
		},
		infoText() {
			if (this.state === states.APPROVABLE) {
				if (this.userName) {
					return t('approval', 'This user requested your approval') + ': '
				} else {
					return t('approval', 'Your approval was requested.')
				}
			}
			return ''
		},
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
		setNode(node) {
			this.node = node
		},
		setUserRules(rules) {
			this.userRules = rules
		},
		onApprove() {
			this.closeModal()
			this.$emit('approve', this.node)
		},
		onReject() {
			this.closeModal()
			this.$emit('reject', this.node)
		},
		onRequest() {
			this.closeModal()
			this.$emit('request', this.node)
		},
	},
}
</script>

<style scoped lang="scss">
.info-modal-content {
	padding: 16px;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	.state-label {
		display: flex;
		align-items: center;
		gap: 4px;
		.details {
			display: flex;
			align-items: center;
			gap: 4px;
		}
	}

	.buttons {
		justify-content: center;
	}

	.approved {
		color: var(--color-success);
	}

	.rejected {
		color: var(--color-error);
	}

	.pending {
		color: var(--color-warning);
	}
}
</style>
