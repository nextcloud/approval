/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { showSuccess, showError, showWarning } from '@nextcloud/dialogs'

export async function getApprovalState(fileId) {
	const url = generateOcsUrl('apps/approval/api/v1/state/{fileId}', { fileId })
	return await axios.get(url)
}

export async function updateNodeApprovalState(node) {
	try {
		const response = await getApprovalState(node.fileid)
		node.attributes['approval-state'] = response.data.ocs.data.state
		node.attributes['approval-rule'] = response.data.ocs.data.rule
		node.attributes['approval-timestamp'] = response.data.ocs.data.timestamp
		node.attributes['approval-userId'] = response.data.ocs.data.userId
		node.attributes['approval-userName'] = response.data.ocs.data.userName
		emit('files:node:updated', node)
	} catch (error) {
		showError(
			t('approval', 'Failed to check approval status'),
		)
		console.error(error)
	}
}

export async function requestApproval(fileId, fileName, ruleId, createShares, node = null) {
	const req = {
		createShares,
	}
	const url = generateOcsUrl('apps/approval/api/v1/request/{fileId}/{ruleId}', { fileId, ruleId })
	try {
		const response = await axios.post(url, req)
		if (createShares) {
			await requestAfterShareCreation(fileId, fileName, ruleId, node)
		} else {
			showSuccess(t('approval', 'Approval requested for {name}', { name: fileName }))
			if (response.data?.ocs?.data?.warning) {
				showWarning(t('approval', 'Warning') + ': ' + response.data.ocs.data.warning)
			}
			if (node) {
				await updateNodeApprovalState(node)
				// TODO
				// reloadTags()
			}
		}
	} catch (error) {
		showError(
			t('approval', 'Failed to request approval for {name}', { name: fileName })
			+ ': ' + (error.response?.data?.ocs?.data?.error ?? error.response?.request?.responseText ?? ''),
		)
		console.error(error)
	}
}

export async function requestAfterShareCreation(fileId, fileName, ruleId, node = null) {
	const req = {
		createShares: false,
	}
	const url = generateOcsUrl('apps/approval/api/v1/request/{fileId}/{ruleId}', { fileId, ruleId })
	try {
		const response = await axios.post(url, req)
		showSuccess(t('approval', 'Approval requested for {name}', { name: fileName }))
		if (response.data?.ocs?.data?.warning) {
			showWarning(t('approval', 'Warning') + ': ' + response.data.ocs.data.warning)
		}
		if (node) {
			await updateNodeApprovalState(node)
			// TODO
			// reloadTags()
		}
	} catch (error) {
		showError(
			t('approval', 'Failed to request approval for {name}', { name: fileName })
			+ ': ' + (error.response?.data?.ocs?.data?.error ?? error.response?.request?.responseText ?? ''),
		)
	}
}

export async function approve(fileId, fileName, node = null, notify = true, message = '') {
	const url = generateOcsUrl('apps/approval/api/v1/approve/{fileId}', { fileId })
	try {
		await axios.put(url, { message })
		if (notify) {
			showSuccess(t('approval', 'You approved {name}', { name: fileName }))
		}
		if (node) {
			await updateNodeApprovalState(node)
		}
	} catch (error) {
		console.error(error)
		if (notify) {
			showError(t('approval', 'Failed to approve {name}', { name: fileName }))
		}
		throw error
	}
}

export async function reject(fileId, fileName, node = null, notify = true, message = '') {
	const url = generateOcsUrl('apps/approval/api/v1/reject/{fileId}', { fileId })
	try {
		await axios.put(url, { message })
		if (notify) {
			showSuccess(t('approval', 'You rejected {name}', { name: fileName }))
		}
		if (node) {
			await updateNodeApprovalState(node)
		}
	} catch (error) {
		console.error(error)
		if (notify) {
			showError(t('approval', 'Failed to reject {name}', { name: fileName }))
		}
		throw error
	}
}

export async function getUserRequesterRules(fileId = null) {
	const req = fileId === null
		? null
		: {
			params: {
				fileId,
			},
		}
	const url = generateOcsUrl('apps/approval/api/v1/user-requester-rules')
	return fileId === null
		? await axios.get(url)
		: await axios.get(url, req)
}

export async function onRequestFileAction(node) {
	const fileId = node.fileid
	const fileName = node.basename
	OCA.Approval.FilesRequestModalVue.showModal()
	// refresh request rules when opening request modal
	try {
		const response = await getUserRequesterRules(fileId)
		OCA.Approval.FilesRequestModalVue.setUserRules(response.data.ocs.data)
		OCA.Approval.FilesRequestModalVue.setNode(node)
		OCA.Approval.userRules = response.data.ocs.data
	} catch (error) {
		console.error(error)
		showError(t('approval', 'Failed to request approval for {name}', { name: fileName }))
	}
}

export async function openApprovalInfoModal(node) {
	OCA.Approval.InfoModalVue.setNode(node)
	OCA.Approval.InfoModalVue.setUserRules([])
	OCA.Approval.InfoModalVue.showModal()
	const response = await getUserRequesterRules(node.fileid)
	OCA.Approval.InfoModalVue.setUserRules(response.data.ocs.data)
}
