<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<div id="approval_prefs" class="section">
			<h2>
				<span class="icon icon-approval" />
				{{ t('approval', 'Approval workflows') }}
			</h2>
			<br>
			<p class="settings-hint">
				{{ t('approval', 'Each workflow defines who (which users, groups or circles) can approve files for a given pending tag and which approved/rejected tag should then be assigned.') }}
			</p>
			<p class="settings-hint">
				{{ t('approval', 'A list of users/groups/circles who can manually request approval can be optionally defined.') }}
			</p>
			<p class="settings-hint">
				{{ t('approval', 'To be considered approved, a file/directory having multiple pending tags assigned must be approved by all the workflows involved.') }}
			</p>
			<p class="settings-hint">
				{{ t('approval', 'You can chain approval workflows by using a pending tag as approved/rejected tag in another workflow.') }}
			</p>
			<p class="settings-hint">
				{{ t('approval', 'All tags must be different in a workflow. A pending tag can only be used in one workflow.') }}
			</p>
			<div v-if="showRules"
				class="rules">
				<ApprovalRule v-for="(rule, id) in rules"
					:key="id"
					v-model:value="rules[id]"
					class="approval-rule"
					@input="onRuleInput(id, $event)"
					@add-tag="onAddTagClick">
					<template #extra-buttons>
						<NcButton
							type="error"
							@click="onRuleDelete(id)">
							<template #icon>
								<DeleteOutlineIcon :size="20" />
							</template>
							{{ t('approval', 'Delete workflow') }}
						</NcButton>
					</template>
				</ApprovalRule>
				<NcEmptyContent v-if="noRules && !loadingRules"
					:title="t('approval', 'No workflow yet')"
					class="no-rules">
					<template #icon>
						<CheckIcon />
					</template>
				</NcEmptyContent>
				<div v-if="newRule" class="new-rule">
					<ApprovalRule
						v-model:value="newRule"
						:delete-rule-label="newRuleDeleteLabel"
						:focus="true"
						@add-tag="onAddTagClick">
						<template #extra-buttons>
							<NcButton
								@click="onNewRuleDelete">
								{{ newRuleDeleteLabel }}
							</NcButton>
							<NcButton
								variant="success"
								:disabled="!newRuleIsValid"
								@click="onValidateNewRule">
								<template #icon>
									<CheckIcon :size="20" />
								</template>
								{{ createTooltip }}
							</NcButton>
						</template>
						<template #extra-footer>
							<p v-if="!newRuleIsValid"
								class="new-rule-error">
								{{ invalidRuleMessage }}
							</p>
						</template>
					</ApprovalRule>
				</div>
			</div>
			<NcButton :class="{ 'add-rule': true, loading: savingRule }"
				:disabled="savingRule"
				@click="onAddRule">
				<template #icon>
					<PlusIcon :size="20" />
				</template>
				{{ t('approval', 'New workflow') }}
			</NcButton>
			<div class="create-tag">
				<label for="create-tag-input">
					<TagIcon :size="16" />
					{{ t('approval', 'Create new restricted tag') }}
				</label>
				<input id="create-tag-input"
					ref="createTagInput"
					v-model="newTagName"
					:placeholder="t('approval', 'New tag name')"
					type="text"
					@keyup.enter="onCreateTag">
				<NcButton :class="{ loading: creatingTag }"
					:disabled="creatingTag"
					@click="onCreateTag">
					<template #icon>
						<PlusIcon :size="20" />
					</template>
					{{ t('approval', 'Create') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'
import TagIcon from 'vue-material-design-icons/Tag.vue'
import DeleteOutlineIcon from 'vue-material-design-icons/DeleteOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcButton from '@nextcloud/vue/components/NcButton'

import ApprovalRule from './ApprovalRule.vue'

import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'AdminSettings',

	components: {
		CheckIcon,
		TagIcon,
		PlusIcon,
		DeleteOutlineIcon,
		ApprovalRule,
		NcEmptyContent,
		NcButton,
	},

	props: [],

	data() {
		return {
			showRules: true,
			newTagName: '',
			rules: {},
			newRule: null,
			creatingTag: false,
			savingRule: false,
			loadingRules: false,
			newRuleDeleteLabel: t('approval', 'Cancel'),
		}
	},

	computed: {
		noRules() {
			return Object.keys(this.rules).length === 0
		},
		newRuleIsValid() {
			return !this.invalidRuleMessage
		},
		invalidRuleMessage() {
			const newRule = this.newRule
			console.debug(newRule)
			const noMissingField = newRule.description
				&& newRule.tagPending
				&& newRule.tagApproved
				&& newRule.tagRejected
				&& newRule.approvers.length > 0
			if (!noMissingField) {
				return t('approval', 'All fields are required')
			}

			if (newRule.tagPending === newRule.tagApproved
				|| newRule.tagPending === newRule.tagRejected
				|| newRule.tagApproved === newRule.tagRejected) {
				return t('approval', 'All tags must be different')
			}

			const conflictingRule = Object.keys(this.rules).find((id) => {
				return this.rules[id].tagPending === newRule.tagPending
			})
			if (conflictingRule) {
				return t('approval', 'Pending tag is already used in another workflow')
			}

			return null
		},
		createTooltip() {
			return t('approval', 'Create workflow')
		},
	},

	watch: {
	},

	mounted() {
		this.loadRules()
	},

	methods: {
		loadRules() {
			this.loadingRules = true
			const url = generateUrl('/apps/approval/rules')
			axios.get(url).then((response) => {
				this.rules = response.data
				// add unique ids to approvers/requesters values
				for (const id in this.rules) {
					this.rules[id].approvers = this.rules[id].approvers.map(a => {
						return {
							...a,
							trackKey: a.type + '-' + a.entityId,
						}
					})
					this.rules[id].requesters = this.rules[id].requesters.map(r => {
						return {
							...r,
							trackKey: r.type + '-' + r.entityId,
						}
					})
				}
			}).catch((error) => {
				showError(
					t('approval', 'Failed to get approval workflows')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? ''),
				)
				console.error(error)
			}).then(() => {
				this.loadingRules = false
			})
		},
		onRuleInput(id, rule) {
			// save if all values are set
			if (rule.description && rule.tagPending && rule.tagApproved && rule.tagRejected && rule.approvers.length > 0) {
				this.savingRule = true
				const req = {
					tagPending: rule.tagPending,
					tagApproved: rule.tagApproved,
					tagRejected: rule.tagRejected,
					description: rule.description,
					approvers: rule.approvers.map((u) => {
						return {
							type: u.type,
							entityId: u.entityId,
						}
					}),
					requesters: rule.requesters.map((u) => {
						return {
							type: u.type,
							entityId: u.entityId,
						}
					}),
					unapproveWhenModified: rule.unapproveWhenModified,
				}
				const url = generateUrl('/apps/approval/rule/' + id)
				axios.put(url, req).then((response) => {
					showSuccess(t('approval', 'Approval workflow saved'))
				}).catch((error) => {
					showError(
						t('approval', 'Failed to save approval workflow')
						+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? ''),
					)
					console.error(error)
					// restore rule values
					this.rules[id] = rule.backupRule
				}).then(() => {
					this.savingRule = false
				})
			}
		},
		onAddRule() {
			this.newRule = {
				tagPending: 0,
				tagApproved: 0,
				tagRejected: 0,
				description: '',
				approvers: [],
				requesters: [],
				unapproveWhenModified: false,
			}
		},
		onNewRuleDelete() {
			this.newRule = null
		},
		onValidateNewRule() {
			const rule = this.newRule
			if (rule.tagPending && rule.tagApproved && rule.tagRejected && rule.approvers.length > 0) {
				this.savingRule = true
				// create
				const req = {
					tagPending: rule.tagPending,
					tagApproved: rule.tagApproved,
					tagRejected: rule.tagRejected,
					description: rule.description,
					approvers: rule.approvers.map((u) => {
						return {
							type: u.type,
							entityId: u.entityId,
						}
					}),
					requesters: rule.requesters.map((u) => {
						return {
							type: u.type,
							entityId: u.entityId,
						}
					}),
					unapproveWhenModified: rule.unapproveWhenModified,
				}
				const url = generateUrl('/apps/approval/rule')
				axios.post(url, req).then((response) => {
					showSuccess(t('approval', 'New approval workflow created'))
					const id = response.data
					this.newRule = null
					this.rules[id] = rule
				}).catch((error) => {
					showError(
						t('approval', 'Failed to create approval workflow')
						+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? ''),
					)
					console.error(error)
				}).then(() => {
					this.savingRule = false
				})
			}
		},
		onRuleDelete(id) {
			const url = generateUrl('/apps/approval/rule/' + id)
			axios.delete(url).then((response) => {
				showSuccess(t('approval', 'Approval workflow deleted'))
				delete this.rules[id]
			}).catch((error) => {
				showError(
					t('approval', 'Failed to delete approval workflow')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? ''),
				)
				console.error(error)
			}).then(() => {
			})
		},
		onCreateTag() {
			if (this.newTagName) {
				this.creatingTag = true
				const req = {
					name: this.newTagName,
				}
				const url = generateUrl('/apps/approval/tag')
				axios.post(url, req).then((response) => {
					showSuccess(t('approval', 'Tag "{name}" created', { name: this.newTagName }))
					this.newTagName = ''
					// trick to reload tag list
					this.showRules = false
					this.$nextTick(() => {
						this.showRules = true
					})
				}).catch((error) => {
					showError(
						t('approval', 'Failed to create tag "{name}"', { name: this.newTagName })
						+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? ''),
					)
					console.error(error)
				}).then(() => {
					this.creatingTag = false
				})
			}
		},
		onAddTagClick() {
			this.$refs.createTagInput.focus()
		},
	},
}
</script>

<style scoped lang="scss">
#approval_prefs {
	.rules {
		margin-top: 20px;
		display: flex;
		flex-wrap: wrap;
	}

	.icon {
		display: inline-block;
		width: 32px;
	}

	.settings-hint {
		.icon {
			margin-bottom: -3px;
		}
		.icon-error {
			padding: 11px 20px;
			vertical-align: text-bottom;
			opacity: 0.5;
		}
	}

	button .icon {
		width: unset;
	}

	.create-tag {
		margin-top: 30px;
		display: flex;
		align-items: center;

		> * {
			margin: 0 4px;
		}

		> label {
			display: flex;
			align-items: center;
			> * {
				margin: 0 4px;
			}
		}

		#create-tag-input {
			margin-left: 3px;
		}
	}

	button.add-rule {
		margin: 0 0 10px 15px;
	}

	.approval-rule,
	.new-rule {
		margin: 15px 15px 15px 15px;
		width: min-content;
		height: min-content;
	}
	.new-rule {
		display: flex;
		align-items: center;
		>button {
			width: 36px;
			min-width: 36px;
			height: 36px;
			padding: 0;
			margin: 0 0 0 5px;
		}
		.new-rule-ok {
			width: max-content;
			margin: 0;
		}
		.new-rule-error {
			margin-top: 16px;
			color: var(--color-text-maxcontrast);
		}
	}
	.no-rules {
		margin-top: 0;
		width: 300px;
	}
}

.icon-approval {
	background-image: url('../../img/app-dark.svg');
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
	filter: var(--background-invert-if-dark);
}

body.theme--dark .icon-approval {
	background-image: url('../../img/app.svg');
}
</style>
