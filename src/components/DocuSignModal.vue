<template>
	<div>
		<Modal v-if="show"
			size="large"
			@close="closeRequestModal">
			PLOP
			{{ fileId }}
		</Modal>
	</div>
</template>

<script>
// import axios from '@nextcloud/axios'
// import { delay } from '../utils'

import Modal from '@nextcloud/vue/dist/Components/Modal'
// import { generateUrl } from '@nextcloud/router'
// import { showSuccess, showError } from '@nextcloud/dialogs'
// import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'DocuSignModal',

	components: {
		Modal,
	},

	props: [],

	data() {
		return {
			show: false,
			fileId: 0,
		}
	},

	computed: {
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
			this.show = false
		},
		setFileId(fileId) {
			this.fileId = fileId
		},
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
