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
					{{ t('approval', 'Users') }}
				</span>
				<MultiselectWho
					class="userInput"
					:value="selectedUsers"
					:max-height="200"
					:types="[0]"
					:placeholder="t('approval', 'Choose Nextcloud users')"
					@update:value="updateSelectedUsers($event)" />
				<span class="field-label">
					<span class="icon icon-mail" />
					{{ t('approval', 'Email addresses (coma separated)') }}
				</span>
				<input v-model="emails"
					:placeholder="t('approval', 'Coma separated email addresses')"
					type="text">
				<p class="settings-hint">
					{{ t('approval', 'Recipients will receive an email from DocuSign with a link to sign the document. You will be informed by email when the document has been signed by all recipients.') }}
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
import Modal from '@nextcloud/vue/dist/Components/Modal'

import MultiselectWho from './MultiselectWho'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'DocuSignModal',

	components: {
		Modal,
		MultiselectWho,
	},

	props: [],

	data() {
		return {
			show: false,
			loading: false,
			fileId: 0,
			emails: '',
			selectedUsers: [],
		}
	},

	computed: {
		emailIsValid() {
			return /^\w+([.+-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,})+(?:,\w+([.+-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,})+)*$/
				.test(this.emails.replace(/\s*,\s*/g, ','))
		},
		canValidate() {
			return this.selectedUsers.length > 0 || this.emailIsValid
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
			this.selectedUsers = []
			this.emails = ''
			this.show = false
		},
		setFileId(fileId) {
			this.fileId = fileId
		},
		updateSelectedUsers(newValue) {
			this.selectedUsers = newValue
			console.debug(this.selectedUsers)
		},
		onSignClick() {
			this.loading = true
			const req = {
				targetUserIds: this.selectedUsers.map((u) => { return u.entityId }),
				targetEmails: this.emails ? this.emails.replace(/\s*,\s*/g, ',').split(',') : undefined,
			}
			const url = generateUrl('/apps/approval/' + this.fileId + '/standalone-sign')
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', 'Signature requested via DocuSign!'))
				this.closeRequestModal()
			}).catch((error) => {
				console.debug(error.response)
				showError(
					t('approval', 'Failed to request signature with DocuSign')
					+ ': ' + (error.response?.data?.response?.message ?? error.response?.data?.error ?? error.response?.request?.responseText ?? '')
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
