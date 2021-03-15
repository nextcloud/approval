<template>
	<div class="approval-container">
		<button v-if="statePending"
			class="success"
			@click="onApprove">
			<span class="icon icon-approve" />
			{{ approveText }}
		</button>
		<button v-if="statePending"
			class="error"
			@click="onReject">
			<span class="icon icon-reject" />
			{{ rejectText }}
		</button>
		<span v-if="stateApproved"
			class="approved-label">
			{{ approvedText }}
		</span>
		<span v-if="stateRejected"
			class="rejected-label">
			{{ rejectedText }}
		</span>
	</div>
</template>

<script>
import { states } from '../states'

export default {
	name: 'ApprovalButtons',

	components: {
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
		state: {
			type: Number,
			required: true,
		},
	},
	data() {
		return {
			myState: 0,
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
	},

	watch: {
		state(state) {
			this.setState(state)
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
	},
}
</script>

<style scoped lang="scss">
.approval-container {
	display: flex;

	.approved-label,
	.rejected-label {
		border: solid 1px var(--color-border-dark);
		margin: 0 5px 0 5px;
		padding: 0 3px 0 3px;
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
