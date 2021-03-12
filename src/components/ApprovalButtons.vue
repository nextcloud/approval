<template>
	<div class="approval-container">
		<button class="success"
			@click="onYes">
			<span class="icon icon-approve" />
			{{ yesText }}
		</button>
		<button class="error"
			@click="onNo">
			<span class="icon icon-reject" />
			{{ noText }}
		</button>
	</div>
</template>

<script>

export default {
	name: 'ApprovalButtons',

	components: {
	},

	props: {
		yesText: {
			type: String,
			default: t('approval', 'Approve'),
		},
		noText: {
			type: String,
			default: t('approval', 'Reject'),
		},
	},
	data() {
		return {
		}
	},

	computed: {
	},

	mounted() {
	},

	methods: {
		onYes() {
			this.$emit('yes')
		},
		onNo() {
			this.$emit('no')
		},
	},
}
</script>

<style scoped lang="scss">
.approval-container {
	display: flex;

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
		margin-bottom: -4px;
	}
}
</style>
