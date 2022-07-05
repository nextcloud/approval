<template>
	<div class="approval_rule">
		<div class="fields">
			<div class="text">
				<span class="field-label main-label">
					<span class="icon icon-checkmark" />
					{{ descriptionLabel }}
				</span>
				<input ref="title"
					type="text"
					:value="value.description"
					:placeholder="descriptionPlaceholder"
					@input="onDescriptionInput">
			</div>
			<div class="tag">
				<span class="field-label">
					<TagIcon :size="16" class="icon" />
					{{ pendingLabel }}
					<div class="spacer" />
					<span v-tooltip.top="{ content: t('approval', 'Create new hidden tag') }"
						class="add-tag-button"
						@click="$emit('add-tag')">
						<span class="icon icon-add" />
					</span>
				</span>
				<MultiselectTags class="tag-select"
					:value="value.tagPending"
					:label="t('approval', 'Select pending tag')"
					:multiple="false"
					@input="update('tagPending', $event)" />
			</div>
			<div class="users">
				<span class="field-label">
					<GroupIcon :size="16" class="icon color-warning" />
					{{ whoRequestLabel }}
				</span>
				<div class="approval-user">
					<MultiselectWho
						class="approval-user-input"
						:value="value.requesters"
						:placeholder="t('approval', 'Who can request approval?')"
						@update:value="update('requesters', $event)" />
				</div>
			</div>
			<div class="users">
				<span class="field-label">
					<GroupIcon :size="16" class="icon color-success" />
					{{ whoApproveLabel }}
				</span>
				<div class="approval-user">
					<MultiselectWho
						class="approval-user-input"
						:value="value.approvers"
						:placeholder="t('approval', 'Who can approve?')"
						@update:value="update('approvers', $event)" />
				</div>
			</div>
			<div class="tag">
				<span class="field-label">
					<TagIcon :size="16" class="icon color-success" />
					{{ approvedLabel }}
					<div class="spacer" />
					<span v-tooltip.top="{ content: t('approval', 'Create new hidden tag') }"
						class="add-tag-button"
						@click="$emit('add-tag')">
						<span class="icon icon-add" />
					</span>
				</span>
				<MultiselectTags class="tag-select"
					:value="value.tagApproved"
					:label="t('approval', 'Select approved tag')"
					:multiple="false"
					@input="update('tagApproved', $event)" />
			</div>
			<div class="tag">
				<span class="field-label">
					<TagIcon :size="16" class="icon color-error" />
					{{ rejectedLabel }}
					<div class="spacer" />
					<span v-tooltip.top="{ content: t('approval', 'Create new hidden tag') }"
						class="add-tag-button"
						@click="$emit('add-tag')">
						<span class="icon icon-add" />
					</span>
				</span>
				<MultiselectTags class="tag-select"
					:value="value.tagRejected"
					:label="t('approval', 'Select rejected tag')"
					:multiple="false"
					@input="update('tagRejected', $event)" />
			</div>
		</div>
		<div class="buttons">
			<slot name="extra-buttons" />
		</div>
		<slot name="extra-footer" />
	</div>
</template>

<script>
import TagIcon from 'vue-material-design-icons/Tag'
import MultiselectTags from '@nextcloud/vue/dist/Components/MultiselectTags'

import { delay } from '../utils'
import MultiselectWho from './MultiselectWho'
import GroupIcon from './icons/GroupIcon'

export default {
	name: 'ApprovalRule',

	components: {
		GroupIcon,
		MultiselectWho,
		MultiselectTags,
		TagIcon,
	},

	props: {
		value: {
			type: Object,
			required: true,
		},
		deleteIcon: {
			type: String,
			default: '',
		},
		focus: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			whoRequestLabel: t('approval', 'Who can request approval'),
			whoApproveLabel: t('approval', 'Who can approve'),
			pendingLabel: t('approval', 'Tag to act on'),
			approvedLabel: t('approval', 'Tag set on approval'),
			rejectedLabel: t('approval', 'Tag set on rejection'),
			descriptionLabel: t('approval', 'Workflow title'),
			descriptionPlaceholder: t('approval', 'What is the purpose of this workflow?'),
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
		if (this.focus) {
			this.$refs.title.focus()
		}
	},

	methods: {
		onDescriptionInput(e) {
			delay(() => {
				this.update('description', e.target.value)
			}, 2000)()
		},
		update(key, value) {
			console.debug(value)
			if (value) {
				const backupRule = {
					...this.value,
					approvers: this.value.approvers.map(e => e),
					requesters: this.value.requesters.map(e => e),
				}
				this.$emit('input', { ...this.value, [key]: value, backupRule })
			}
		},
	},
}
</script>

<style scoped lang="scss">
.approval_rule {
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
	padding: 16px;

	.color-warning {
		color: var(--color-warning);
	}
	.color-success {
		color: var(--color-success);
	}
	.color-error {
		color: var(--color-error);
	}

	.buttons {
		display: flex;
		align-items: center;
		> * {
			margin-right: 4px;
		}
	}

	.fields {
		display: flex;
		flex-direction: column;

		.tag,
		.text,
		.users {
			display: flex;
			flex-direction: column;
			// align-items: center;
			margin: 0 0 16px 0;
			.field-label {
				margin: 0 0 5px 0;
			}
		}

		.text {
			input {
				width: 250px;
			}
		}

		.field-label {
			display: flex;
			align-items: center;
			margin-right: 5px;
			width: 250px;
			.spacer {
				flex-grow: 1;
			}
		}

		.main-label {
			font-weight: bold;
		}

		.tag-select {
			width: 250px;
		}

		.add-tag-button {
			margin: 0;
			height: 26px;
			min-height: 10px;
			width: 26px;
			padding: 0;
			border: none;
			border-radius: 50%;
			background-color: transparent;
			&:hover {
				background-color: var(--color-background-darker);
			}
			> .icon {
				opacity: 0.5;
			}
		}
	}

	.icon {
		width: 16px;
		height: 16px;
		margin: 0 5px -3px 5px;
		display: inline-block;
	}
	button .icon {
		margin: 0;
	}
	.approval-user-input {
		width: 250px;
	}
}
</style>
