/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import FilesRequestModal from '../components/FilesRequestModal.vue'
import InfoModal from '../components/InfoModal.vue'
import { requestApproval, approve, reject, onRequestFileAction } from './helpers.js'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

export function createFilesRequestModal() {
	const filesRequestModalId = 'filesRequestApprovalModal'
	const filesRequestModalElement = document.createElement('div')
	filesRequestModalElement.id = filesRequestModalId
	document.body.append(filesRequestModalElement)

	const app = createApp(FilesRequestModal, {
		onClose: () => {
			console.debug('[Approval] modal closed')
		},
		onRequest: (node, ruleId, createShares) => {
			requestApproval(node.fileid, node.basename, ruleId, createShares, node)
		},
	})
	app.mixin({ methods: { t, n } })
	OCA.Approval.FilesRequestModalVue = app.mount(filesRequestModalElement)
	return app
}

export function createInfoModal() {
	const infoModalId = 'approvalInfoModal'
	const infoModalElement = document.createElement('div')
	infoModalElement.id = infoModalId
	document.body.append(infoModalElement)

	const app = createApp(InfoModal, {
		onClose: () => {
			console.debug('[Approval] modal closed')
		},
		onApprove: (node, message) => {
			approve(node.fileid, node.basename, node, true, message)
		},
		onReject: (node, message) => {
			reject(node.fileid, node.basename, node, true, message)
		},
		onRequest: (node) => {
			onRequestFileAction(node)
		},
	})
	app.mixin({ methods: { t, n } })
	OCA.Approval.InfoModalVue = app.mount(infoModalElement)

	// Return the app instance for potential cleanup
	return app
}
