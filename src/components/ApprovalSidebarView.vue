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
		<span v-if="canRequestApproval">
			<button @click="showRequestModal">
				{{ requestLabel }}
			</button>
			<Modal v-if="requestModal" @close="closeRequestModal">
				<RequestForm :rules="userRules"
					@request="onRequest" />
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
		userId: {
			type: String,
			default: '',
		},
		userName: {
			type: String,
			default: '',
		},
		datetime: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
			myState: 0,
			myUserId: null,
			myUserName: null,
			myDatetime: null,
			you: t('approval', 'you'),
			userRules: [],
			requestLabel: t('approval', 'Request approval'),
			requestModal: false,
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
		onRequest(ruleId) {
			this.closeRequestModal()
			this.$emit('request', ruleId)
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
		height: 34px;
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
		width: 32px;
		height: 32px;
		border-radius: var(--border-radius-pill);
	}

	.icon-close-white,
	.icon-checkmark-white {
		margin-right: 5px;
	}
}
</style>
