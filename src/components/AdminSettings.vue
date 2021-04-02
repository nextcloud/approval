<template>
	<div id="approval_prefs" class="section">
		<h2>
			<span class="icon icon-approval" />
			{{ t('approval', 'Approval rules') }}
		</h2>
		<br>
		<p class="settings-hint">
			{{ t('approval', 'Each rule specifies who (which users, groups or circles) can act on which pending tag and which approved/rejected tag should then be assigned.') }}
		</p>
		<p class="settings-hint">
			{{ t('approval', 'You can chain approval rules by using a pending tag as approved/rejected tag in another rule.') }}
		</p>
		<p class="settings-hint">
			{{ t('approval', 'All tags must be different in a rule. A pending tag can only be used in one rule.') }}
		</p>
		<div v-if="showRules"
			class="rules">
			<ApprovalRule v-for="(rule, id) in rules"
				:key="id"
				v-model="rules[id]"
				class="approval-rule"
				@input="onRuleInput(id, $event)"
				@delete="onRuleDelete(id)" />
			<EmptyContent v-if="noRules && !loadingRules"
				class="no-rules"
				icon="icon-approval">
				{{ t('approval', 'No rules yet') }}
			</EmptyContent>
		</div>
		<button :class="{ 'add-rule': true, loading: savingRule }"
			:disabled="savingRule"
			@click="onAddRule">
			<span class="icon icon-add" />
			{{ t('approval', 'New rule') }}
		</button>
		<div v-if="newRule && showRules" class="new-rule">
			<ApprovalRule
				v-model="newRule"
				delete-icon="icon-history"
				@delete="onNewRuleDelete" />
			<button v-if="newRuleIsValid"
				v-tooltip.top="{ content: createTooltip }"
				@click="onValidateNewRule">
				<span class="icon icon-checkmark-color" />
			</button>
			<span v-else
				class="new-rule-error">
				{{ invalidRuleMessage }}
			</span>
		</div>
		<div class="create-tag">
			<label for="create-tag-input">
				<span class="icon icon-tag" />
				{{ t('approval', 'Create new hidden tag') }}
			</label>
			<input id="create-tag-input"
				v-model="newTagName"
				:placeholder="t('approval', 'New tag name')"
				type="text"
				@keyup.enter="onCreateTag">
			<button :class="{ loading: creatingTag }"
				:disabled="creatingTag"
				@click="onCreateTag">
				<span class="icon icon-add" />
				{{ t('approval', 'Create') }}
			</button>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

import ApprovalRule from './ApprovalRule'

export default {
	name: 'AdminSettings',

	components: {
		ApprovalRule, EmptyContent,
	},

	props: [],

	data() {
		return {
			state: loadState('approval', 'admin-config'),
			showRules: true,
			newTagName: '',
			rules: {},
			newRule: null,
			creatingTag: false,
			savingRule: false,
			loadingRules: false,
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
			const noMissingField = newRule.tagPending && newRule.tagApproved && newRule.tagRejected && newRule.who.length > 0
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
				return t('approval', 'Pending tag is already used in another rule')
			}

			return null
		},
		createTooltip() {
			return t('approval', 'Create this rule')
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
				// add unique ids to who values
				for (const id in this.rules) {
					this.rules[id].who = this.rules[id].who.map(w => {
						return {
							...w,
							trackKey: w.userId
								? 'user-' + w.userId
								: w.groupId
									? 'group-' + w.groupId
									: 'circle-' + w.circleId,
						}
					})
				}
			}).catch((error) => {
				showError(
					t('approval', 'Failed to get approval rules')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			}).then(() => {
				this.loadingRules = false
			})
		},
		onRuleInput(id, rule) {
			// save if all values are set
			if (rule.tagPending && rule.tagApproved && rule.tagRejected && rule.who.length > 0) {
				this.savingRule = true
				const req = {
					tagPending: rule.tagPending,
					tagApproved: rule.tagApproved,
					tagRejected: rule.tagRejected,
					who: rule.who.map((u) => {
						return {
							userId: u.userId,
							groupId: u.groupId,
							circleId: u.circleId,
						}
					}),
				}
				const url = generateUrl('/apps/approval/rule/' + id)
				axios.put(url, req).then((response) => {
					showSuccess(t('approval', 'Approval rule saved'))
				}).catch((error) => {
					showError(
						t('approval', 'Failed to save approval rule')
						+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
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
				who: [],
			}
		},
		onNewRuleDelete() {
			this.newRule = null
		},
		onValidateNewRule() {
			const rule = this.newRule
			if (rule.tagPending && rule.tagApproved && rule.tagRejected && rule.who.length > 0) {
				this.savingRule = true
				// create
				const req = {
					tagPending: rule.tagPending,
					tagApproved: rule.tagApproved,
					tagRejected: rule.tagRejected,
					who: rule.who.map((u) => {
						return {
							userId: u.userId,
							groupId: u.groupId,
							circleId: u.circleId,
						}
					}),
				}
				const url = generateUrl('/apps/approval/rule')
				axios.post(url, req).then((response) => {
					showSuccess(t('approval', 'New approval rule created'))
					const id = response.data
					this.newRule = null
					this.$set(this.rules, id, rule)
				}).catch((error) => {
					showError(
						t('approval', 'Failed to create approval rule')
						+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
					)
					console.debug(error)
				}).then(() => {
					this.savingRule = false
				})
			}
		},
		onRuleDelete(id) {
			const url = generateUrl('/apps/approval/rule/' + id)
			axios.delete(url).then((response) => {
				showSuccess(t('approval', 'Approval rule deleted'))
				this.$delete(this.rules, id)
			}).catch((error) => {
				showError(
					t('approval', 'Failed to delete approval rule')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
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
						+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
					)
					console.debug(error)
				}).then(() => {
					this.creatingTag = false
				})
			}
		},
	},
}
</script>

<style scoped lang="scss">
::v-deep .multiselect__input {
	height: 34px !important;
}

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
	}

	button.add-rule {
		margin: 0 0 10px 15px;
	}

	#create-tag-input {
		margin-left: 3px;
	}

	.approval-rule,
	.new-rule {
		margin: 15px 15px 15px 15px;
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
		.new-rule-error {
			margin-left: 10px;
			padding: 0 5px 0 5px;
			color: white;
			background-color: var(--color-warning);
			border-radius: var(--border-radius);
		}
	}
	.no-rules {
		margin-top: 0;
		width: 300px;
	}
}

.icon-approval {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
}

body.theme--dark .icon-approval {
	background-image: url(./../../img/app.svg);
}
</style>
