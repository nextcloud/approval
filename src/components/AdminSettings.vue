<template>
	<div id="approval_prefs" class="section">
		<h2>
			<span class="icon icon-approval" />
			{{ t('approval', 'Approval') }}
		</h2>
		<p class="settings-hint">
			{{ t('approval', '') }}
		</p>
		<div class="approval-user">
			<label for="user">
				<span class="icon icon-user" />
				{{ t('approval', 'User') }}
			</label>
			<Multiselect
				class="approval-user-input"
				label="displayName"
				:clear-on-select="true"
				:hide-selected="false"
				:internal-search="false"
				:loading="loadingUsers"
				:options="formattedSuggestions"
				:placeholder="t('welcome', 'Who can approve?')"
				:preselect-first="false"
				:preserve-search="false"
				:searchable="true"
				:user-select="true"
				@search-change="asyncFind"
				@select="supportContactSelected">
				<template #option="{option}">
					<Avatar
						class="approval-avatar-option"
						:user="option.user"
						:show-user-status="false" />
					<span>
						{{ option.displayName }}
					</span>
				</template>
				<template #noOptions>
					{{ t('welcome', 'No recommendations. Start typing.') }}
				</template>
				<template #noResult>
					{{ t('welcome', 'No result.') }}
				</template>
			</Multiselect>
			<div v-if="state.user_name && state.user_id"
				class="selected-user">
				<Avatar
					:size="20"
					:user="state.user_id"
					:tooltip-message="state.user_id"
					:show-user-status="false" />
				<span>
					{{ state.user_name }}
				</span>
			</div>
		</div>
		<div class="grid-form">
			<label for="pending">
				<span class="icon" :style="'background-image: url(' + tagPendingIconUrl + ');'" />
				{{ t('approval', 'Pending tag') }}
			</label>
			<MultiselectTags id="pending"
				v-model="state.tag_pending"
				:label="t('approval', 'Select pending tag')"
				:multiple="false"
				@input="onTagInput" />
			<label for="approved">
				<span class="icon" :style="'background-image: url(' + tagApprovedIconUrl + ');'" />
				{{ t('approval', 'Approved tag') }}
			</label>
			<MultiselectTags id="approved"
				v-model="state.tag_approved"
				:label="t('approval', 'Select approved tag')"
				:multiple="false"
				@input="onTagInput" />
			<label for="rejected">
				<span class="icon" :style="'background-image: url(' + tagRejectedIconUrl + ');'" />
				{{ t('approval', 'Rejected tag') }}
			</label>
			<MultiselectTags id="rejected"
				v-model="state.tag_rejected"
				:label="t('approval', 'Select rejected tag')"
				:multiple="false"
				@input="onTagInput" />
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
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import MultiselectTags from '@nextcloud/vue/dist/Components/MultiselectTags'

export default {
	name: 'AdminSettings',

	components: {
		Avatar,
		Multiselect,
		MultiselectTags,
	},

	props: [],

	data() {
		return {
			state: loadState('approval', 'admin-config'),
			tagPendingIconUrl: generateUrl('/svg/core/actions/tag?color=0082c9'),
			tagApprovedIconUrl: generateUrl('/svg/core/actions/tag?color=46ba61'),
			tagRejectedIconUrl: generateUrl('/svg/core/actions/tag?color=e9322d'),
			loadingUsers: false,
			suggestions: [],
			query: '',
			currentUser: getCurrentUser(),
			newTagName: '',
		}
	},

	computed: {
		formattedSuggestions() {
			const result = this.suggestions.map((s) => {
				return {
					user: s.id,
					displayName: s.label,
					icon: 'icon-user',
					multiselectKey: s.id + s.label,
				}
			})
			if (this.currentUser) {
				const lowerCurrent = this.currentUser.displayName.toLowerCase()
				const lowerQuery = this.query.toLowerCase()
				if (this.query === '' || lowerCurrent.match(lowerQuery)) {
					result.push({
						user: this.currentUser.uid,
						displayName: this.currentUser.displayName,
						icon: 'icon-user',
						multiselectKey: this.currentUser.uid + this.currentUser.displayName,
					})
				}
			}
			return result
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onTagInput() {
			if (this.state.tag_pending && this.state.tag_approved && this.state.tag_rejected) {
				const values = {
					tag_pending: this.state.tag_pending,
					tag_approved: this.state.tag_approved,
					tag_rejected: this.state.tag_rejected,
				}
				this.saveOptions(values)
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
		asyncFind(query) {
			this.query = query
			if (query === '') {
				this.suggestions = []
				return
			}
			this.loadingUsers = true
			console.debug(query)
			const url = generateOcsUrl('core/autocomplete/get', 2).replace(/\/$/, '')
			axios.get(url, {
				params: {
					format: 'json',
					search: query,
					itemType: ' ',
					itemId: ' ',
					shareTypes: [],
				},
			}).then((response) => {
				console.debug(response)
				this.suggestions = response.data.ocs.data
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.loadingUsers = false
			})
		},
		supportContactSelected(user) {
			console.debug(user)
			this.state.user_id = user.user
			this.state.user_name = user.displayName
			this.saveOptions({
				user_id: this.state.user_id,
				user_name: this.state.user_name,
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
	},
}
</script>

<style scoped lang="scss">
#approval_prefs {
	.icon {
		display: inline-block;
		width: 32px;
	}

	.approval-user {
		margin-left: 30px;
		display: flex;
		> label {
			width: 250px;
		}
		.selected-user {
			display: flex;
			align-items: center;
			* {
				margin-left: 5px;
			}
		}
	}

	.grid-form {
		max-width: 500px;
		display: grid;
		grid-template: 1fr / 1fr 1fr;
		margin-left: 30px;

		.icon {
			width: 16px;
			height: 16px;
			margin: 0 5px -3px 5px;
		}

		label {
			line-height: 38px;
		}

		input {
			width: 100%;
		}
	}

	.create-tag {
		margin-top: 20px;

		button .icon {
			width: unset;
		}
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
