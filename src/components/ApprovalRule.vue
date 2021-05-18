<template>
	<div class="approval_rule">
		<div class="fields">
			<div class="text">
				<span class="field-label main-label">
					<span class="icon icon-checkmark" />
					{{ descriptionLabel }}
				</span>
				<input type="text"
					:value="value.description"
					:placeholder="descriptionPlaceholder"
					@input="onDescriptionInput">
			</div>
			<div class="tag">
				<span class="field-label">
					<span class="icon" :style="'background-image: url(' + tagPendingIconUrl + ');'" />
					{{ pendingLabel }}
					<button v-tooltip.top="{ content: t('approval', 'Create new hidden tag') }"
						class="add-tag-button"
						@click="$emit('add-tag')">
						<span class="icon icon-add" />
					</button>
				</span>
				<MultiselectTags class="tag-select"
					:value="value.tagPending"
					:label="t('approval', 'Select pending tag')"
					:multiple="false"
					@input="update('tagPending', $event)" />
			</div>
			<div class="users">
				<span class="field-label">
					<span class="icon" :style="'background-image: url(' + groupYellowIconUrl + ');'" />
					{{ whoRequestLabel }}
				</span>
				<div class="approval-user">
					<MultiselectWho
						class="approval-user-input"
						:value="value.requesters"
						:placeholder="t('welcome', 'Who can request approval?')"
						@update:value="update('requesters', $event)" />
				</div>
			</div>
			<div class="users">
				<span class="field-label">
					<span class="icon" :style="'background-image: url(' + groupGreenIconUrl + ');'" />
					{{ whoApproveLabel }}
				</span>
				<div class="approval-user">
					<MultiselectWho
						class="approval-user-input"
						:value="value.approvers"
						:placeholder="t('welcome', 'Who can approve?')"
						@update:value="update('approvers', $event)" />
				</div>
			</div>
			<div class="tag">
				<span class="field-label">
					<span class="icon" :style="'background-image: url(' + tagApprovedIconUrl + ');'" />
					{{ approvedLabel }}
					<button v-tooltip.top="{ content: t('approval', 'Create new hidden tag') }"
						class="add-tag-button"
						@click="$emit('add-tag')">
						<span class="icon icon-add" />
					</button>
				</span>
				<MultiselectTags class="tag-select"
					:value="value.tagApproved"
					:label="t('approval', 'Select approved tag')"
					:multiple="false"
					@input="update('tagApproved', $event)" />
			</div>
			<div class="tag">
				<span class="field-label">
					<span class="icon" :style="'background-image: url(' + tagRejectedIconUrl + ');'" />
					{{ rejectedLabel }}
					<button v-tooltip.top="{ content: t('approval', 'Create new hidden tag') }"
						class="add-tag-button"
						@click="$emit('add-tag')">
						<span class="icon icon-add" />
					</button>
				</span>
				<MultiselectTags class="tag-select"
					:value="value.tagRejected"
					:label="t('approval', 'Select rejected tag')"
					:multiple="false"
					@input="update('tagRejected', $event)" />
			</div>
		</div>
		<div class="buttons">
			<button
				class="delete-rule"
				@click="$emit('delete')">
				<span :class="'icon ' + deleteIcon" />
				{{ deleteRuleLabel }}
			</button>
			<slot name="extra-buttons" />
		</div>
		<slot name="extra-footer" />
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import MultiselectTags from '@nextcloud/vue/dist/Components/MultiselectTags'

import { delay } from '../utils'
import MultiselectWho from './MultiselectWho'

export default {
	name: 'ApprovalRule',

	components: {
		MultiselectWho,
		MultiselectTags,
	},

	props: {
		value: {
			type: Object,
			required: true,
		},
		deleteIcon: {
			type: String,
			default: 'icon-delete',
		},
		deleteRuleLabel: {
			type: String,
			default: t('approval', 'Delete rule'),
		},
	},

	data() {
		return {
			tagPendingIconUrl: generateUrl('/svg/core/actions/tag?color=767676'),
			tagApprovedIconUrl: generateUrl('/svg/core/actions/tag?color=46ba61'),
			tagRejectedIconUrl: generateUrl('/svg/core/actions/tag?color=e9322d'),
			groupGreenIconUrl: generateUrl('/svg/core/actions/group?color=46ba61'),
			groupYellowIconUrl: generateUrl('/svg/core/actions/group?color=767676'),
			whoRequestLabel: t('approval', 'Who can request approval'),
			whoApproveLabel: t('approval', 'Who can approve'),
			pendingLabel: t('approval', 'Tag to act on'),
			approvedLabel: t('approval', 'Tag set on approval'),
			rejectedLabel: t('approval', 'Tag set on rejection'),
			descriptionLabel: t('approval', 'Rule title'),
			descriptionPlaceholder: t('approval', 'What is the purpose of this rule?'),
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
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
			margin-right: 5px;
			width: 250px;
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
			background-color: transparent;
			&:hover {
				background-color: var(--color-background-darker);
			}
		}
	}

	.delete-rule {
		width: max-content;
		color: var(--color-error);
		border-color: var(--color-error);
		margin: 0;
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
