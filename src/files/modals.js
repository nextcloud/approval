/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import FilesRequestModal from '../components/FilesRequestModal.vue'
import InfoModal from '../components/InfoModal.vue'
import { requestApproval, approve, reject, onRequestFileAction } from './helpers.js'

/**
 * Create and mount the files request modal
 */
export function createFilesRequestModal() {
	const filesRequestModalId = 'filesRequestApprovalModal'
	const filesRequestModalElement = document.createElement('div')
	filesRequestModalElement.id = filesRequestModalId
	document.body.append(filesRequestModalElement)

	// Create and mount the Vue 3 app
	const app = createApp(FilesRequestModal)
	OCA.Approval.FilesRequestModalVue = app.mount(filesRequestModalElement)

	// Set up event listeners using the Vue 3 event emitter pattern
	OCA.Approval.FilesRequestModalVue.emitter.on('close', () => {
		console.debug('[Approval] modal closed')
	})

	OCA.Approval.FilesRequestModalVue.emitter.on('request', (node, ruleId, createShares) => {
		requestApproval(node.fileid, node.basename, ruleId, createShares, node)
	})

	// Return the app instance for potential cleanup
	return app
}

/**
 * Create and mount the info modal
 */
export function createInfoModal() {
	const infoModalId = 'approvalInfoModal'
	const infoModalElement = document.createElement('div')
	infoModalElement.id = infoModalId
	document.body.append(infoModalElement)

	// Create and mount the Vue 3 app
	const app = createApp(InfoModal)
	OCA.Approval.InfoModalVue = app.mount(infoModalElement)

	// Set up event listeners using the Vue 3 event emitter pattern
	OCA.Approval.InfoModalVue.emitter.on('close', () => {
		console.debug('[Approval] modal closed')
	})

	OCA.Approval.InfoModalVue.emitter.on('approve', (node) => {
		approve(node.fileid, node.basename, node)
	})

	OCA.Approval.InfoModalVue.emitter.on('reject', (node) => {
		reject(node.fileid, node.basename, node)
	})

	OCA.Approval.InfoModalVue.emitter.on('request', (node) => {
		onRequestFileAction(node)
	})

	// Return the app instance for potential cleanup
	return app
}
