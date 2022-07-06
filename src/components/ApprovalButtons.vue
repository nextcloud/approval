<template>
	<div class="buttons">
		<Button
			type="success"
			@click="$emit('approve')">
			<template #icon>
				<CheckIcon :size="20" />
			</template>
			{{ approveText }}
		</Button>
		<Button
			type="error"
			@click="$emit('reject')">
			<template #icon>
				<CloseIcon :size="20" />
			</template>
			{{ rejectText }}
		</Button>
	</div>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check'
import CloseIcon from 'vue-material-design-icons/Close'
import Button from '@nextcloud/vue/dist/Components/Button'

export default {
	name: 'ApprovalButtons',

	components: {
		Button,
		CheckIcon,
		CloseIcon,
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
		&:first-child {
			margin-left: 0;
		}
	}
}
</style>
