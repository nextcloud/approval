<template>
	<div class="buttons">
		<button
			class="success"
			@click="$emit('approve')">
			<span class="icon icon-checkmark-white" />
			{{ approveText }}
		</button>
		<button
			class="error"
			@click="$emit('reject')">
			<span class="icon icon-close-white" />
			{{ rejectText }}
		</button>
	</div>
</template>

<script>

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
		state: {
			type: Number,
			required: true,
		},
	},
	data() {
		return {
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.buttons {
	width: 100%;
	display: flex;
	// align-items: center;
	// justify-content: center;

	button {
		margin: 0 4px 0 4px;
		min-height: 44px;
		&:first-child {
			margin-left: 0;
		}
		&.success {
			background-color: var(--color-success) !important;
			border-color: var(--color-success) !important;
			color: #fff !important;
		}

		&.error,
		&.success {
			&:hover,
			&:focus {
				border-color: var(--color-main-text) !important;
			}
		}
		.icon {
			opacity: 1;
		}
	}
}
</style>
