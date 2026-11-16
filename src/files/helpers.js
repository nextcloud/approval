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

/**
 * Check the freshly fetched state for an existing vote of the current user
 * and show a toast when there is one, so callers can abort instead of calling
 * the approve/reject endpoint (which would return HTTP 400 anyway).
 * Legacy rules report no `myVote` key at all and always pass.
 * @param {object} node the file node
 * @return {Promise<boolean>} true when the caller must abort
 */
export async function abortIfAlreadyVoted(node) {
	try {
		const response = await getApprovalState(node.fileid)
		const myVote = response.data?.ocs?.data?.myVote
		if (myVote !== undefined && myVote !== null) {
			showError(t('approval', 'You already voted on {name}', { name: node.basename }))
			return true
		}
	} catch (error) {
		console.error(error)
	}
	return false
}

export async function updateNodeApprovalState(node) {
	try {
		const response = await getApprovalState(node.fileid)
		node.attributes['approval-state'] = response.data.ocs.data.state
		node.attributes['approval-rule'] = response.data.ocs.data.rule
		node.attributes['approval-timestamp'] = response.data.ocs.data.timestamp
		node.attributes['approval-userId'] = response.data.ocs.data.userId
		node.attributes['approval-userName'] = response.data.ocs.data.userName
		// ratio mode only: absent (undefined) on legacy rules
		node.attributes['approval-progress'] = response.data.ocs.data.progress
		node.attributes['approval-myVote'] = response.data.ocs.data.myVote
		node.attributes['approval-threshold'] = response.data.ocs.data.threshold
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

export async function approve(node, notify = true, message = '') {
	const url = generateOcsUrl('apps/approval/api/v1/approve/{fileId}', { fileId: node.fileid })
	try {
		await axios.put(url, { message, etag: node.attributes.etag })
		if (notify) {
			showSuccess(t('approval', 'You approved {name}', { name: node.basename }))
		}
		await updateNodeApprovalState(node)
	} catch (error) {
		console.error(error)
		if (notify) {
			showError(error.response.data?.ocs?.data?.error ?? t('approval', 'Failed to approve {name}', { name: node.basename }))
		}
		throw error
	}
}

export async function reject(node, notify = true, message = '') {
	const url = generateOcsUrl('apps/approval/api/v1/reject/{fileId}', { fileId: node.fileid })
	try {
		await axios.put(url, { message, etag: node.attributes.etag })
		if (notify) {
			showSuccess(t('approval', 'You rejected {name}', { name: node.basename }))
		}
		await updateNodeApprovalState(node)
	} catch (error) {
		console.error(error)
		if (notify) {
			showError(error.response.data?.ocs?.data?.error ?? t('approval', 'Failed to reject {name}', { name: node.basename }))
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
