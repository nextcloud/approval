<template>
	<div id="approval_prefs" class="section">
		<h2>
			<span class="icon icon-approval" />
			{{ t('approval', 'Approval') }}
		</h2>
		<h3>
			<span class="icon icon-settings" />
			{{ t('approval', 'Approval rules') }}
		</h3>
		<p class="settings-hint">
			{{ t('approval', 'Each rule specifies which users can act on which pending tag and which approved/rejected tag should then be assigned.') }}
		</p>
		<div v-if="showRules">
			<ApprovalRule v-for="(rule, id) in rules"
				:key="id"
				v-model="rules[id]"
				class="approval-rule"
				@input="onRuleInput(id, $event)"
				@delete="onRuleDelete(id)" />
			<button class="add-rule"
				@click="onAddRule">
				<span class="icon icon-add" />
				{{ t('approval', 'New rule') }}
			</button>
			<ApprovalRule v-if="newRule"
				v-model="newRule"
				class="approval-rule"
				@input="onNewRuleInput"
				@delete="onNewRuleDelete" />
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
			<button @click="onCreateTag">
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

import ApprovalRule from './ApprovalRule'

export default {
	name: 'AdminSettings',

	components: {
		ApprovalRule,
	},

	props: [],

	data() {
		return {
			state: loadState('approval', 'admin-config'),
			showRules: true,
			newTagName: '',
			rules: {},
			newRule: null,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
		this.loadRules()
	},

	methods: {
		loadRules() {
			const url = generateUrl('/apps/approval/rules')
			axios.get(url).then((response) => {
				this.rules = response.data
			}).catch((error) => {
				showError(
					t('approval', 'Failed to get approval rules')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			}).then(() => {
			})
		},
		onRuleInput(id, rule) {
			// save if all values are set
			if (rule.tagPending && rule.tagApproved && rule.tagRejected && rule.users.length > 0) {
				const req = {
					tagPending: rule.tagPending,
					tagApproved: rule.tagApproved,
					tagRejected: rule.tagRejected,
					users: rule.users.map(u => u.user),
				}
				const url = generateUrl('/apps/approval/rule/' + id)
				axios.put(url, req).then((response) => {
					showSuccess(t('approval', 'Approval rule saved'))
				}).catch((error) => {
					showError(
						t('approval', 'Failed to save approval rule')
						+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
					)
					console.debug(error)
				}).then(() => {
				})
			}
		},
		onAddRule() {
			this.newRule = {
				tagPending: 0,
				tagApproved: 0,
				tagRejected: 0,
				users: [],
			}
		},
		onNewRuleDelete() {
			this.newRule = null
		},
		onNewRuleInput(rule) {
			console.debug(rule)
			if (rule.tagPending && rule.tagApproved && rule.tagRejected && rule.users.length > 0) {
				// create
				const req = {
					tagPending: rule.tagPending,
					tagApproved: rule.tagApproved,
					tagRejected: rule.tagRejected,
					users: rule.users.map(u => u.user),
				}
				const url = generateUrl('/apps/approval/rule')
				axios.post(url, req).then((response) => {
					showSuccess(t('approval', 'New approval rule created'))
					const id = response.data
					this.newRule = null
					this.rules[id] = rule
				}).catch((error) => {
					showError(
						t('approval', 'Failed to create approval rule')
						+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
					)
					console.debug(error)
				}).then(() => {
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
	.icon {
		display: inline-block;
		width: 32px;
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

	.approval-rule {
		margin: 12px 0 12px 0;
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
