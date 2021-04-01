<template>
	<div class="approval-container">
		<button v-if="stateApprovable"
			class="success"
			@click="onApprove">
			<span class="icon icon-approve" />
			{{ approveText }}
		</button>
		<button v-if="stateApprovable"
			class="error"
			@click="onReject">
			<span class="icon icon-reject" />
			{{ rejectText }}
		</button>
		<span v-if="stateApproved"
			class="state-label approved-label">
			<span class="icon icon-approve" />
			<span>{{ approvedText }}</span>
			<span v-if="myUserId && myDatetime"
				class="details">
				by
				<UserBubble v-if="notMe"
					class="user-bubble"
					:user="myUserId"
					:display-name="myUserName"
					:size="24" />
				<span v-else>
					{{ you }}
				</span>
				{{ relativeTime }}
			</span>
		</span>
		<span v-if="stateRejected"
			class="state-label rejected-label">
			<span class="icon icon-reject" />
			<span>{{ rejectedText }}</span>
			<span v-if="myUserId && myDatetime"
				class="details">
				by
				<UserBubble
					class="user-bubble"
					:user="myUserId"
					:display-name="myUserName"
					:size="24" />
				{{ relativeTime }}
			</span>
		</span>
		<span v-if="statePending"
			class="state-label pending-label">
			{{ pendingText }}
		</span>
	</div>
</template>

<script>
import { states } from '../states'

import moment from '@nextcloud/moment'
import { getCurrentUser } from '@nextcloud/auth'
import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'

export default {
	name: 'ApprovalButtons',

	components: {
		UserBubble,
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
		rejectedText: {
			type: String,
			default: t('approval', 'Rejected'),
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
			return moment(this.myDatetime).fromNow()
		},
		notMe() {
			return this.myUserId !== getCurrentUser().uid
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
	},
}
</script>

<style scoped lang="scss">
.approval-container {
	display: flex;
	margin: 5px 0 5px 0;

	button {
		margin-top: 0;
		margin-bottom: 0;
	}

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
			}
		}
	}

	.pending-label,
	.approved-label,
	.rejected-label {
		border: solid 1px var(--color-border-dark);
		margin: 0 5px 0 5px;
		padding: 0 3px 0 3px;
	}

	.pending-label {
		background-color: var(--color-warning) !important;
		border-color: var(--color-warning) !important;
		color: #fff !important;
	}

	.rejected-label {
		background-color: var(--color-error) !important;
		border-color: var(--color-error) !important;
		color: #fff !important;
	}

	.approved-label,
	button.success {
		background-color: var(--color-success) !important;
		border-color: var(--color-success) !important;
		color: #fff !important;
	}

	.icon-approve,
	.icon-reject {
		background-image: url('../../img/app.svg');
		background-size: 16px 16px;
		margin-right: 5px;
	}
	.icon-reject {
		-webkit-transform: rotate(180deg);
		-moz-transform: rotate(180deg);
		-ms-transform: rotate(0deg);
		-o-transform: rotate(180deg);
		transform: rotate(180deg);
		margin-bottom: -2px;
	}
}
</style>
