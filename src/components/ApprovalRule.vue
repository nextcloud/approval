<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
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
					<span
						:title="t('approval', 'Create new hidden tag')"
						class="add-tag-button"
						@click="$emit('add-tag')">
						<span class="icon icon-add" />
					</span>
				</span>
				<NcSelectTags
					class="tag-select"
					:model-value="value.tagPending"
					:placeholder="t('approval', 'Select pending tag')"
					:multiple="false"
					:close-on-select="true"
					:clear-search-on-select="true"
					:append-to-body="false"
					:aria-label-combobox="pendingLabel"
					:limit="null"
					@update:model-value="update('tagPending', $event)" />
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
						:aria-label-combobox="whoRequestLabel"
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
						:aria-label-combobox="whoApproveLabel"
						@update:value="update('approvers', $event)" />
				</div>
			</div>
			<div class="tag">
				<span class="field-label">
					<TagIcon :size="16" class="icon color-success" />
					{{ approvedLabel }}
					<div class="spacer" />
					<span
						:title="t('approval', 'Create new hidden tag')"
						class="add-tag-button"
						@click="$emit('add-tag')">
						<span class="icon icon-add" />
					</span>
				</span>
				<NcSelectTags
					class="tag-select"
					:model-value="value.tagApproved"
					:placeholder="t('approval', 'Select approved tag')"
					:multiple="false"
					:close-on-select="true"
					:clear-search-on-select="true"
					:aria-label-combobox="approvedLabel"
					:limit="null"
					@update:model-value="update('tagApproved', $event)" />
			</div>
			<div class="tag">
				<span class="field-label">
					<TagIcon :size="16" class="icon color-error" />
					{{ rejectedLabel }}
					<div class="spacer" />
					<span
						:title="t('approval', 'Create new hidden tag')"
						class="add-tag-button"
						@click="$emit('add-tag')">
						<span class="icon icon-add" />
					</span>
				</span>
				<NcSelectTags
					class="tag-select"
					:model-value="value.tagRejected"
					:placeholder="t('approval', 'Select rejected tag')"
					:multiple="false"
					:close-on-select="true"
					:clear-search-on-select="true"
					:aria-label-combobox="rejectedLabel"
					:limit="null"
					@update:model-value="update('tagRejected', $event)" />
			</div>
			<div class="checkbox">
				<NcCheckboxRadioSwitch
					:model-value="value.unapproveWhenModified"
					type="switch"
					@update:model-value="update('unapproveWhenModified', $event)">
					{{ checkboxLabel }}
				</NcCheckboxRadioSwitch>
			</div>
		</div>
		<div class="buttons">
			<slot name="extra-buttons" />
		</div>
		<slot name="extra-footer" />
	</div>
</template>

<script>
import TagIcon from 'vue-material-design-icons/Tag.vue'

import NcSelectTags from '@nextcloud/vue/components/NcSelectTags'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import { delay } from '../utils.js'
import MultiselectWho from './MultiselectWho.vue'
import GroupIcon from './icons/GroupIcon.vue'

export default {
	name: 'ApprovalRule',

	components: {
		GroupIcon,
		MultiselectWho,
		NcSelectTags,
		TagIcon,
		NcCheckboxRadioSwitch,
	},

	props: {
		value: {
			type: Object,
			required: true,
		},
		focus: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['add-tag', 'input', 'update:value'],

	data() {
		return {
			whoRequestLabel: t('approval', 'Who can request approval'),
			whoApproveLabel: t('approval', 'Who can approve'),
			pendingLabel: t('approval', 'Tag to act on'),
			approvedLabel: t('approval', 'Tag set on approval'),
			rejectedLabel: t('approval', 'Tag set on rejection'),
			descriptionLabel: t('approval', 'Workflow title'),
			checkboxLabel: t('approval', 'Remove file approval when file is modified'),
			descriptionPlaceholder: t(
				'approval',
				'What is the purpose of this workflow?',
			),
		}
	},

	computed: {},

	watch: {},

	mounted() {
		if (this.focus) {
			this.$refs.title.focus()
		}
	},

	methods: {
		resetFocus() {
			this.$refs.title.focus()
		},
		onDescriptionInput(e) {
			delay(() => {
				this.update('description', e.target.value)
			}, 2000)()
		},
		update(key, value) {
			console.debug('update', key, value)
			if (value || value === false) {
				const backupRule = {
					...this.value,
					approvers: this.value.approvers.map((e) => e),
					requesters: this.value.requesters.map((e) => e),
				}
				this.$emit('update:value', { ...this.value, [key]: value })
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
	.checkbox,
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
        width: 310px;
      }
    }

    .field-label {
      display: flex;
      align-items: center;
      margin-right: 5px;
      width: 310px;
      .spacer {
        flex-grow: 1;
      }
    }

    .main-label {
      font-weight: bold;
    }

    .tag-select {
      width: 310px;
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
    width: 310px;
  }
}
</style>
