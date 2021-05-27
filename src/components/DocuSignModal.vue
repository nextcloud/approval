<template>
	<div class="docusign-modal-container">
		<Modal v-if="show"
			size="large"
			@close="closeRequestModal">
			<div class="docusign-modal-content">
				<h2>
					{{ t('approval', 'Request a signature via DocuSign') }}
				</h2>
				<span class="field-label">
					<span class="icon icon-user" />
					{{ t('approval', 'User') }}
				</span>
				<Multiselect
					v-model="user"
					class="userInput"
					label="displayName"
					track-by="multiselectKey"
					:max-height="200"
					:disabled="email !== ''"
					:placeholder="t('approval', 'Choose a Nextcloud user')"
					:options="formattedSuggestions"
					:user-select="true"
					:internal-search="true"
					@search-change="asyncFind">
					<template #option="{option}">
						<Avatar
							class="approval-avatar-option"
							:user="option.entityId"
							:show-user-status="false" />
						<span class="multiselect-name">
							{{ option.displayName }}
						</span>
						<span
							class="icon icon-user multiselect-icon" />
					</template>
				</Multiselect>
				<span class="field-label">
					<span class="icon icon-mail" />
					{{ t('approval', 'Email address') }}
				</span>
				<input v-model="email"
					:disabled="user !== null"
					:placeholder="t('approval', 'or an email address')"
					type="text">
				<p class="settings-hint">
					{{ t('approval', 'The target person will receive an email from DocuSign with a link to sign the document. You will be informed by email when the document has been signed.') }}
				</p>
				<div class="docusign-footer">
					<button
						@click="closeRequestModal">
						{{ t('approval', 'Cancel') }}
					</button>
					<button class="primary"
						:class="{ loading }"
						:disabled="!canValidate"
						@click="onSignClick">
						{{ t('approval', 'Request signature') }}
					</button>
				</div>
			</div>
		</Modal>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
// import { delay } from '../utils'

import Modal from '@nextcloud/vue/dist/Components/Modal'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'DocuSignModal',

	components: {
		Avatar,
		Modal,
		Multiselect,
	},

	props: [],

	data() {
		return {
			show: false,
			loading: false,
			fileId: 0,
			email: '',
			user: null,
			suggestions: [],
		}
	},

	computed: {
		formattedSuggestions() {
			return this.suggestions.map((s) => {
				return {
					entityId: s.id,
					type: 'user',
					displayName: s.label,
					icon: 'icon-user',
					multiselectKey: 'user-' + s.id,
				}
			})
		},
		emailIsValid() {
			return /^\w+([.+-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,})+$/.test(this.email)
		},
		canValidate() {
			return this.user || this.emailIsValid
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		showModal() {
			this.show = true
		},
		closeRequestModal() {
			this.user = null
			this.email = ''
			this.show = false
		},
		setFileId(fileId) {
			this.fileId = fileId
		},
		asyncFind(query) {
			this.query = query
			if (query === '') {
				this.suggestions = []
				return
			}
			this.loadingSuggestions = true
			const url = generateOcsUrl('core/autocomplete/get', 2).replace(/\/$/, '')
			axios.get(url, {
				params: {
					format: 'json',
					search: query,
					itemType: ' ',
					itemId: ' ',
					// users
					shareTypes: [0],
				},
			}).then((response) => {
				this.suggestions = response.data.ocs.data
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.loadingSuggestions = false
			})
		},
		onSignClick() {
			this.loading = true
			const req = {
				targetUserId: this.user?.entityId,
				targetEmail: this.email ? this.email : undefined,
			}
			const url = generateUrl('/apps/approval/' + this.fileId + '/standalone-sign')
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', 'Signature requested via DocuSign!'))
				this.closeRequestModal()
			}).catch((error) => {
				showError(
					t('approval', 'Failed to request signature with DocuSign')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
			}).then(() => {
				this.loading = false
			})
		},
	},
}
</script>

<style scoped lang="scss">
.docusign-modal-content {
	padding: 16px;
	// min-height: 400px;
	max-width: 400px;
	display: flex;
	flex-direction: column;

	input[type='text'] {
		width: 100%;
	}

	.userInput {
		width: 100%;
		.multiselect-name {
			flex-grow: 1;
			margin-left: 10px;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		.multiselect-icon {
			opacity: 0.5;
		}
	}

	.settings-hint {
		color: var(--color-text-maxcontrast);
		margin: 16px 0 16px 0;
	}
}

.docusign-footer {
	margin-top: 16px;
	.primary {
		float: right;
	}
	.icon {
		opacity: 1;
	}
}

.field-label {
	display: flex;
	align-items: center;
	height: 36px;
	.icon {
		width: 32px;
	}
}
</style>
