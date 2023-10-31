<template>
	<div class="docusign-modal-container">
		<NcModal v-if="show"
			size="large"
			@close="closeRequestModal">
			<div class="docusign-modal-content">
				<h2>
					{{ t('approval', 'Request a signature via DocuSign') }}
				</h2>
				<span class="field-label">
					{{ t('approval', 'Users or email addresses') }}
				</span>
				<MultiselectWho
					ref="multiselect"
					class="userInput"
					:value="selectedItems"
					:max-height="200"
					:types="[0]"
					:enable-emails="true"
					:placeholder="t('approval', 'Nextcloud users or email addresses')"
					@update:value="updateSelectedItems($event)" />
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
		</NcModal>
	</div>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import MultiselectWho from './MultiselectWho.vue'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'DocuSignModal',

	components: {
		NcModal,
		MultiselectWho,
	},

	props: [],

	data() {
		return {
			show: false,
			loading: false,
			fileId: 0,
			selectedItems: [],
		}
	},

	computed: {
		canValidate() {
			return this.selectedItems.length > 0
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		showModal() {
			this.show = true
			// once the modal is opened, focus on the multiselect input
			this.$nextTick(() => {
				this.$refs.multiselect.$el.querySelector('input').focus()
			})
		},
		closeRequestModal() {
			this.selectedItems = []
			this.show = false
		},
		setFileId(fileId) {
			this.fileId = fileId
		},
		updateSelectedItems(newValue) {
			this.selectedItems = newValue
			console.debug(this.selectedItems)
		},
		onSignClick() {
			this.loading = true

			const targetUserIds = this.selectedItems.filter((i) => { return i.type === 'user' }).map((i) => { return i.entityId })
			const targetEmails = this.selectedItems.filter((i) => { return i.type === 'email' }).map((i) => { return i.email })
			const req = {
				targetUserIds,
				targetEmails,
			}
			const url = generateUrl('/apps/approval/docusign/standalone-sign/' + this.fileId)
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', 'Recipients will receive an email from DocuSign to sign the document'))
				this.closeRequestModal()
			}).catch((error) => {
				console.debug(error.response)
				showError(
					t('approval', 'Failed to request signature with DocuSign')
					+ ': ' + (error.response?.data?.response?.message ?? error.response?.data?.error ?? error.response?.request?.responseText ?? ''),
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
		margin: 0 0 28px 0;
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
	margin: 8px 0 0 0;
	.icon {
		width: 32px;
	}
}
</style>
