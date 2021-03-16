<template>
	<div id="approval_prefs" class="section">
		<h2>
			<span class="icon icon-approval" />
			{{ t('approval', 'Approval') }}
		</h2>
		<p class="settings-hint">
			{{ t('approval', '') }}
		</p>
		<ApprovalSetting v-for="(setting, id) in settings"
			:key="id"
			v-model="settings[id]"
			@input="onSettingInput(id, $event)"
			@delete="onSettingDelete(id)" />
		<button @click="onAddSetting">
			<span class="icon icon-add" />
			{{ t('approval', 'New setting') }}
		</button>
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
			newTagName: '',
			settings: {},
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
		// TODO correctly load multiple values from state
		this.settings = {
			33: {
				tagPending: this.state.tag_pending,
				tagApproved: this.state.tag_approved,
				tagRejected: this.state.tag_rejected,
				users: this.state.user_id
					? [
						{
							user: this.state.user_id,
							displayName: this.state.user_name,
						},
					]
					: [],
			},
		}
	},

	methods: {
		onSettingInput(id, setting) {
			console.debug('INPUTTUTUTU ' + id)
			console.debug(setting)
			console.debug(this.settings)
			// TODO correctly save values, send setting ID etc...
			this.saveOptions({
				tag_pending: setting.tagPending,
				tag_approved: setting.tagApproved,
				tag_rejected: setting.tagRejected,
				user_id: setting.users[0]?.user || '',
				user_name: setting.users[0]?.displayName || '',
			})
			// save if all values are set
			if (setting.tagPending && setting.tagApproved && setting.tagRejected && setting.users.length > 0) {
				// save or create
			}
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/approval/admin-config')
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', 'Approval admin options saved'))
			}).catch((error) => {
				showError(
					t('approval', 'Failed to save Approval admin options')
					+ ': ' + (error.response?.request?.responseText ?? '')
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
					location.reload()
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
		onAddSetting() {
			this.$set(this.settings, 44, {
				tagPending: this.state.tag_pending,
				tagApproved: this.state.tag_approved,
				tagRejected: this.state.tag_rejected,
				users: this.state.user_id
					? [
						{
							user: this.state.user_id,
							displayName: this.state.user_name,
						},
					]
					: [],
			})
			console.debug(this.settings)
		},
		onSettingDelete(id) {
			this.$delete(this.settings, id)
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
		margin-top: 20px;
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
