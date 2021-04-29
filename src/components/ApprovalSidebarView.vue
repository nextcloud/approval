<template>
	<div class="approval-container">
		<ApprovalButtons v-if="stateApprovable"
			:state="state"
			:approve-text="approveText"
			:reject-text="rejectText"
			@approve="$emit('approve')"
			@reject="$emit('reject')" />
		<span v-if="stateApproved"
			class="state-label approved-label">
			<span class="icon icon-checkmark-white" />
			<span v-if="myUserId && myDatetime"
				class="details">
				<strong>{{ approvedByText }}</strong>
				<UserBubble
					class="user-bubble"
					:user="myUserId"
					:display-name="notMe ? myUserName : you"
					:size="24" />
				{{ relativeTime }}
			</span>
			<span v-else>{{ approvedText }}</span>
		</span>
		<span v-if="stateRejected"
			class="state-label rejected-label">
			<span class="icon icon-close-white" />
			<span v-if="myUserId && myDatetime"
				class="details">
				<strong>{{ rejectedByText }}</strong>
				<UserBubble
					class="user-bubble"
					:user="myUserId"
					:display-name="notMe ? myUserName : you"
					:size="24" />
				{{ relativeTime }}
			</span>
			<span v-else>{{ rejectedText }}</span>
		</span>
		<span v-if="statePending"
			class="state-label pending-label">
			<span class="icon icon-checkmark-white" />
			<span v-if="myUserId && myDatetime"
				class="details">
				<strong>{{ requestedByText }}</strong>
				<UserBubble
					class="user-bubble"
					:user="myUserId"
					:display-name="notMe ? myUserName : you"
					:size="24" />
				{{ relativeTime }}
			</span>
			<span v-else>{{ pendingText }}</span>
		</span>
		<span v-if="canRequestApproval"
			class="request-button-wrapper">
			<button @click="showRequestModal">
				{{ requestLabel }}
			</button>
			<Modal v-if="requestModal" @close="closeRequestModal">
				<RequestForm :rules="userRules"
					class="info-modal"
					@request="onRequest" />
			</Modal>
		</span>
		<span v-if="myRule">
			<button class="icon icon-details info-button" @click="showInfoModal" />
			<Modal v-if="infoModal" @close="closeInfoModal">
				<div class="info-modal">
					<p>
						{{ infoText }}
						<br><br>
					</p>
					<p>
						{{ myRule.description }}
					</p>
				</div>
			</Modal>
		</span>
	</div>
</template>

<script>
import { states } from '../states'

import moment from '@nextcloud/moment'
import { getCurrentUser } from '@nextcloud/auth'
import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'
import Modal from '@nextcloud/vue/dist/Components/Modal'

import ApprovalButtons from './ApprovalButtons'
import RequestForm from './RequestForm'

export default {
	name: 'ApprovalSidebarView',

	components: {
		UserBubble, Modal, ApprovalButtons, RequestForm,
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
			default: t('approval', 'Pending approval'),
		},
		state: {
			type: Number,
			required: true,
		},
	},
	data() {
		return {
			myState: 0,
			myUserId: null,
			myUserName: null,
			myDatetime: null,
			myRule: null,
			you: t('approval', 'you'),
			userRules: [],
			requestLabel: t('approval', 'Request approval'),
			requestModal: false,
			infoModal: false,
		}
	},

	computed: {
		stateApproved() {
			return this.myState === states.APPROVED
		},
		stateRejected() {
			return this.myState === states.REJECTED
		},
		statePending() {
			return this.myState === states.PENDING
		},
		stateApprovable() {
			return this.myState === states.APPROVABLE
		},
		relativeTime() {
			return moment.unix(this.myDatetime).fromNow()
		},
		notMe() {
			return this.myUserId !== getCurrentUser().uid
		},
		canRequestApproval() {
			return this.myState === states.NOTHING && this.userRules.length > 0
		},
		infoText() {
			if (this.myState === states.APPROVABLE) {
				return t('approval', 'Your approval was requested. The related approval rule description is:')
			} else if ([states.APPROVED, states.PENDING, states.REJECTED].includes(this.myState)) {
				return t('approval', 'The related approval rule description is:')
			}
			return ''
		},
	},

	watch: {
		state(state) {
			this.setState(state)
		},
		userId(userId) {
			this.setUserId(userId)
		},
		userName(userName) {
			this.setUserName(userName)
		},
		datetime(datetime) {
			this.setDatetime(datetime)
		},
	},

	mounted() {
	},

	methods: {
		onApprove() {
			this.$emit('approve')
		},
		onReject() {
			this.$emit('reject')
		},
		setState(state) {
			this.myState = state
		},
		setRule(rule) {
			this.myRule = rule
		},
		setUserId(userId) {
			this.myUserId = userId
		},
		setUserName(userName) {
			this.myUserName = userName
		},
		setDatetime(datetime) {
			this.myDatetime = datetime
		},
		setUserRules(rules) {
			this.userRules = rules
		},
		showRequestModal() {
			this.requestModal = true
		},
		closeRequestModal() {
			this.requestModal = false
		},
		onRequest(ruleId, createShares) {
			this.closeRequestModal()
			this.$emit('request', ruleId, createShares)
		},
		showInfoModal() {
			this.infoModal = true
		},
		closeInfoModal() {
			this.infoModal = false
		},
	},
}
</script>

<style scoped lang="scss">
.approval-container {
	display: flex;
	margin: 5px 0 15px 0;

	.state-label {
		border-radius: var(--border-radius);
		height: 44px;
		width: 100%;
		display: flex;
		align-items: center;
		.details {
			height: 24px;
			margin-left: 2px;
			.user-bubble {
				color: var(--color-main-text);
				height: 26px;
			}
		}
	}

	.pending-label .icon {
		background-color: var(--color-warning) !important;
		border-color: var(--color-warning) !important;
		color: #fff !important;
	}

	.rejected-label .icon {
		background-color: var(--color-error) !important;
		border-color: var(--color-error) !important;
		color: #fff !important;
	}

	.approved-label .icon {
		background-color: var(--color-success) !important;
		border-color: var(--color-success) !important;
		color: #fff !important;
	}

	.pending-label .icon,
	.approved-label .icon,
	.rejected-label .icon {
		min-width: 32px;
		min-height: 32px;
		width: 32px;
		height: 32px;
		border-radius: var(--border-radius-pill);
	}

	.icon-close-white,
	.icon-checkmark-white {
		margin-right: 5px;
	}

	.request-button-wrapper {
		width: 100%;
		button {
			height: 44px;
		}
	}

	.info-button {
		height: 44px;
		width: 44px;
		margin: 0;
		border: none;
		background-color: transparent;
		&:hover {
			background-color: var(--color-background-dark);
		}
	}
}

::v-deep.info-modal {
	padding: 15px;
}
</style>
