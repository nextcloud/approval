<template>
	<div class="approval-container">
		<div v-if="stateApprovable"
			class="buttons">
			<button
				class="success"
				@click="onApprove">
				<span class="icon icon-approve" />
				{{ approveText }}
			</button>
			<button
				class="error"
				@click="onReject">
				<span class="icon icon-reject" />
				{{ rejectText }}
			</button>
		</div>
		<span v-if="stateApproved"
			class="state-label approved-label">
			<span class="icon icon-approve" />
			<span v-if="myUserId && myDatetime"
				class="details">
				<b>{{ approvedByText }}</b>
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
			<span class="icon icon-reject" />
			<span v-if="myUserId && myDatetime"
				class="details">
				<b>{{ rejectedByText }}</b>
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
			<span class="icon icon-pending" />
			<b>{{ pendingText }}</b>
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

	.buttons {
		width: 100%;
		display: flex;
		align-items: center;
		justify-content: center;

		button {
			margin: 0 5px 0 5px;
		}
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
		margin: 0 5px 0 5px;
		padding: 0 3px 0 3px;
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

	.approved-label .icon,
	button.success {
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

	.icon-pending {
		background-image: url('../../img/pending.svg');
		background-size: 23px;
		margin-right: 5px;
	}
	.icon-approve,
	.icon-reject {
		background-image: url('../../img/app.svg');
		background-size: 16px;
		margin-right: 5px;
	}
	.icon-reject {
		-webkit-transform: rotate(180deg);
		-moz-transform: rotate(180deg);
		-ms-transform: rotate(0deg);
		-o-transform: rotate(180deg);
		transform: rotate(180deg);
		background-position-y: 6px;
	}
}
</style>
