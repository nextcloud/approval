<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="request-form">
		<h2>
			{{ title }}
		</h2>
		<p class="settings-hint">
			{{ createShareHint }}
		</p>
		<fieldset class="rule-list">
			<label v-for="r in rules"
				:key="r.id"
				:class="{ 'rule-selected': selectedRule === r.id }"
				:for="'rule-' + r.id">
				<div>
					<input :id="'rule-' + r.id"
						v-model="selectedRule"
						name="approval-rule"
						:value="r.id"
						type="radio">
					<span class="rule-title">
						{{ r.description }}
					</span>
				</div>
				<div class="approvers">
					<span>
						{{ approversLabel }}
					</span>
					<NcUserBubble v-for="approver in r.approvers"
						:key="approver.type + '-' + approver.entityId"
						class="user-bubble"
						:user="approver.type === 'user' ? approver.entityId : undefined"
						:display-name="approver.displayName"
						:avatar-image="getAvatarImageClass(approver)"
						:size="24" />
				</div>
			</label>
		</fieldset>
		<div class="footer">
			<NcButton
				@click="$emit('cancel')">
				{{ cancelLabel }}
			</NcButton>
			<div class="spacer" />
			<NcButton
				variant="primary"
				:disabled="!selectedRule"
				@click="$emit('request', selectedRule, true)">
				<template #icon>
					<CheckIcon :size="20" />
				</template>
				{{ requestLabel }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'

import NcUserBubble from '@nextcloud/vue/components/NcUserBubble'
import NcButton from '@nextcloud/vue/components/NcButton'

export default {
	name: 'RequestForm',

	components: {
		NcUserBubble,
		NcButton,
		CheckIcon,
	},

	props: {
		rules: {
			type: Array,
			required: true,
		},
	},

	emits: ['request', 'cancel'],

	data() {
		return {
			selectedRule: null,
			title: t('approval', 'Select approval workflow'),
			requestLabel: t('approval', 'Request approval'),
			cancelLabel: t('approval', 'Cancel'),
			createShares: false,
			createShareHint: t('approval', 'File will be automatically shared with everybody allowed to approve.'),
			approversLabel: t('approval', 'Can be approved by'),
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
		if (this.rules && this.rules.length === 1) {
			this.selectedRule = this.rules[0].id
		}
	},

	methods: {
		getAvatarImageClass(entity) {
			if (entity.type === 'group') {
				return 'icon-group'
			} else if (entity.type === 'circle') {
				return 'icon-circles'
			}
			return undefined
		},
	},
}
</script>

<style scoped lang="scss">
.request-form {
	>h2 {
		margin-top: 0;
	}

	.rule-list {
		display: flex;
		flex-direction: column;
		* {
			cursor: pointer;
		}

		.approvers label,
		.rule-title {
			margin-left: 8px;
		}
		.rule-title {
			font-weight: bold;
		}

		>label {
			padding: 0 8px 0 12px;
			margin-bottom: 4px;
			&:focus,
			&:hover,
			&.rule-selected {
				background-color: var(--color-background-hover);
				border-radius: var(--border-radius-large);
			}
			div {
				display: flex;
				align-items: center;
				&.approvers {
					flex-wrap: wrap;
					margin: -4px 0 8px 24px;
					.user-bubble {
						color: var(--color-main-text);
						height: 28px;
						margin-left: 4px;
					}
				}
			}
		}
		input[type=radio] {
			margin: 0;
			&:focus {
				// no way to make it look different
			}
		}
	}

	.settings-hint {
		margin: 0 0 16px 0;
		color: var(--color-text-maxcontrast);
		.icon {
			display: inline-block;
		}
	}

	.footer {
		display: flex;
		align-items: center;
		margin-top: 16px;
		.spacer {
			flex-grow: 1;
		}
	}
}
</style>
