<template>
	<div id="approval_prefs" class="section">
		<h2>
			<span class="icon icon-approval" />
			{{ t('approval', 'Approval') }}
		</h2>
		<h3>
			<span class="icon icon-settings" />
			{{ t('approval', 'Approval settings') }}
		</h3>
		<p class="settings-hint">
			{{ t('approval', 'Each setting specifies which users can act on which pending tag and which approved/rejected tag should then be assigned.') }}
		</p>
		<div v-if="showSettings">
			<ApprovalSetting v-for="(setting, id) in settings"
				:key="id"
				v-model="settings[id]"
				@input="onSettingInput(id, $event)"
				@delete="onSettingDelete(id)" />
			<button class="add-setting"
				@click="onAddSetting">
				<span class="icon icon-add" />
				{{ t('approval', 'New setting') }}
			</button>
			<ApprovalSetting v-if="newSetting"
				v-model="newSetting"
				@input="onNewSettingInput"
				@delete="onNewSettingDelete" />
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

import ApprovalSetting from './ApprovalSetting'

export default {
	name: 'AdminSettings',

	components: {
		ApprovalSetting,
	},

	props: [],

	data() {
		return {
			state: loadState('approval', 'admin-config'),
			showSettings: true,
			newTagName: '',
			settings: {},
			newSetting: null,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
		this.loadSettings()
	},

	methods: {
		loadSettings() {
			const url = generateUrl('/apps/approval/settings')
			axios.get(url).then((response) => {
				this.settings = response.data
			}).catch((error) => {
				showError(
					t('approval', 'Failed to get approval setting')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			}).then(() => {
			})
		},
		onSettingInput(id, setting) {
			// save if all values are set
			if (setting.tagPending && setting.tagApproved && setting.tagRejected && setting.users.length > 0) {
				const req = {
					tagPending: setting.tagPending,
					tagApproved: setting.tagApproved,
					tagRejected: setting.tagRejected,
					users: setting.users.map(u => u.user),
				}
				const url = generateUrl('/apps/approval/setting/' + id)
				axios.put(url, req).then((response) => {
					showSuccess(t('approval', 'Approval setting saved'))
				}).catch((error) => {
					showError(
						t('approval', 'Failed to save approval setting')
						+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
					)
					console.debug(error)
				}).then(() => {
				})
			}
		},
		onAddSetting() {
			this.newSetting = {
				tagPending: 0,
				tagApproved: 0,
				tagRejected: 0,
				users: [],
			}
		},
		onNewSettingDelete() {
			this.newSetting = null
		},
		onNewSettingInput(setting) {
			console.debug(setting)
			if (setting.tagPending && setting.tagApproved && setting.tagRejected && setting.users.length > 0) {
				// create
				const req = {
					tagPending: setting.tagPending,
					tagApproved: setting.tagApproved,
					tagRejected: setting.tagRejected,
					users: setting.users.map(u => u.user),
				}
				const url = generateUrl('/apps/approval/setting')
				axios.post(url, req).then((response) => {
					showSuccess(t('approval', 'New approval setting created'))
					const id = response.data
					this.newSetting = null
					this.settings[id] = setting
				}).catch((error) => {
					showError(
						t('approval', 'Failed to create approval setting')
						+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
					)
					console.debug(error)
				}).then(() => {
				})
			}
		},
		onSettingDelete(id) {
			const url = generateUrl('/apps/approval/setting/' + id)
			axios.delete(url).then((response) => {
				showSuccess(t('approval', 'Approval setting deleted'))
				this.$delete(this.settings, id)
			}).catch((error) => {
				showError(
					t('approval', 'Failed to delete approval setting')
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
					this.showSettings = false
					this.$nextTick(() => {
						this.showSettings = true
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

	button.add-setting {
		margin: 10px 0 10px 15px;
	}

	#create-tag-input {
		margin-left: 3px;
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
