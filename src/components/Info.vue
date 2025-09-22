<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="info-content">
		<NcButton v-if="canRequestApproval" @click="onRequest">
			<template #icon>
				<CheckIcon :size="20" />
			</template>
			{{ t("approval", "Request approval") }}
		</NcButton>
		<div v-else-if="stateNothing">
			{{
				t(
					"approval",
					"There is no approval workflow allowing you to request approval."
				)
			}}
		</div>
		<NcInputField v-if="stateApprovable" v-model="newMessage" :label="t('approval', 'Reason (optional)')" />
		<ApprovalButtons
			v-if="stateApprovable"
			class="buttons"
			:approve-text="approveText"
			:reject-text="rejectText"
			@approve="onApprove"
			@reject="onReject" />
		<span v-if="stateApproved" class="state-label approved-label">
			<CheckCircleIcon class="approved" :size="32" />
			<span v-if="userId && timestamp" class="details">
				<strong>{{ approvedByText }}</strong>
				{{ relativeTime }}
			</span>
			<span v-else>{{ approvedText }}</span>
		</span>
		<span v-if="stateRejected" class="state-label rejected-label">
			<CloseCircleIcon class="rejected" :size="32" />
			<span v-if="userId && timestamp" class="details">
				<strong>{{ rejectedByText }}</strong>
				{{ relativeTime }}
			</span>
			<span v-else>{{ rejectedText }}</span>
		</span>
		<span v-if="message" class="state-label">
			<MessageDrawIcon />{{ messageText }}
		</span>
		<span v-if="statePending" class="state-label pending-label">
			<DotsHorizontalCircleIcon class="pending" :size="32" />
			<span v-if="userId && timestamp" class="details">
				<strong>{{ requestedByText }}</strong>
				{{ relativeTime }}
			</span>
			<span v-else>{{ pendingTextWithTime }}</span>
		</span>
		<div class="info">
			<span>{{ infoText }}</span>
			<NcUserBubble
				v-if="stateApprovable && userName"
				class="user-bubble"
				:user="userId"
				:display-name="notMe ? userName : you"
				:size="24" />
			<span>{{ ruleText }}</span>
		</div>
	</div>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CheckCircleIcon from 'vue-material-design-icons/CheckCircle.vue'
import DotsHorizontalCircleIcon from 'vue-material-design-icons/DotsHorizontalCircle.vue'
import CloseCircleIcon from 'vue-material-design-icons/CloseCircle.vue'
import MessageDrawIcon from 'vue-material-design-icons/MessageDraw.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcUserBubble from '@nextcloud/vue/components/NcUserBubble'
import NcInputField from '@nextcloud/vue/components/NcInputField'

import ApprovalButtons from './ApprovalButtons.vue'

import { states } from '../states.js'

import { getCurrentUser } from '@nextcloud/auth'
import moment from '@nextcloud/moment'

export default {
	name: 'Info',

	components: {
		ApprovalButtons,
		NcButton,
		NcUserBubble,
		NcInputField,
		CheckCircleIcon,
		CloseCircleIcon,
		CheckIcon,
		DotsHorizontalCircleIcon,
		MessageDrawIcon,
	},

	props: {
		state: {
			type: Number,
			required: true,
		},
		timestamp: {
			type: [Number, null],
			default: null,
		},
		message: {
			type: String,
			default: '',
		},
		userName: {
			type: [String, null],
			default: null,
		},
		userId: {
			type: [String, null],
			default: null,
		},
		rule: {
			type: Object,
			default: () => {},
		},
		userRules: {
			type: Array,
			required: true,
		},
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
		rejectedText: {
			type: String,
			default: t('approval', 'Rejected'),
		},
		pendingText: {
			type: String,
			default: t('approval', 'Approval requested'),
		},
	},

	emits: ['request', 'approve', 'reject'],

	data() {
		return {
			you: t('approval', 'you'),
			newMessage: '',
		}
	},

	computed: {
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
		stateNothing() {
			return this.state === states.NOTHING
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
			if (
				[
					states.APPROVED,
					states.PENDING,
					states.REJECTED,
					states.APPROVABLE,
				].includes(this.state)
			) {
				return t(
					'approval',
					'The related approval workflow is: {ruleDescription}',
					{ ruleDescription: this.rule.description },
				)
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
		approvedByText() {
			return this.notMe
				? t('approval', 'Approved by {user}', { user: this.userName })
				: t('approval', 'Approved by you')
		},
		rejectedByText() {
			return this.notMe
				? t('approval', 'Rejected by {user}', { user: this.userName })
				: t('approval', 'Rejected by you')
		},
		requestedByText() {
			return this.notMe
				? t('approval', 'Approval requested by {user}', { user: this.userName })
				: t('approval', 'Approval requested by you')
		},
		messageText() {
			if (this.stateApproved) {
				return t('approval', 'Reason for approval: {message}', { message: this.message })
			}
			if (this.stateRejected) {
				return t('approval', 'Reason for rejection: {message}', { message: this.message })
			}
			return this.message
		},
	},

	watch: {},

	mounted() {},

	methods: {
		onApprove() {
			this.$emit('approve', this.newMessage)
		},
		onReject() {
			this.$emit('reject', this.newMessage)
		},
		onRequest() {
			this.$emit('request')
		},
	},
}
</script>

<style scoped lang="scss">
.info-content {
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
