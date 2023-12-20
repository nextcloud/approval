import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { showSuccess, showError, showWarning } from '@nextcloud/dialogs'
import { set as vueSet } from 'vue'

export function getApprovalState(node) {
	const url = generateOcsUrl('apps/approval/api/v1/state/' + node.fileid, 2)
	return axios.get(url)
}

export function updateNodeApprovalState(node) {
	return getApprovalState(node).then(response => {
		const state = response.data.ocs.data.state
		vueSet(node.attributes, 'approval-state', state)
		vueSet(node.attributes, 'approval-rule', response.data.ocs.data.rule)
		vueSet(node.attributes, 'approval-timestamp', response.data.ocs.data.timestamp)
		vueSet(node.attributes, 'approval-userId', response.data.ocs.data.userId)
		vueSet(node.attributes, 'approval-userName', response.data.ocs.data.userName)
		emit('files:node:updated', node)
	}).catch((error) => {
		showError(
			t('approval', 'Failed to check approval status'),
		)
		console.error(error)
	})
}

export function requestApproval(node, ruleId, createShares) {
	const fileId = node.fileid
	const fileName = node.basename
	const req = {
		createShares,
	}
	const url = generateOcsUrl('apps/approval/api/v1/request/' + fileId + '/' + ruleId, 2)
	axios.post(url, req).then((response) => {
		if (createShares) {
			requestAfterShareCreation(node, ruleId)
		} else {
			showSuccess(t('approval', 'Approval requested for {name}', { name: fileName }))
			if (response.data?.ocs?.data?.warning) {
				showWarning(t('approval', 'Warning') + ': ' + response.data.ocs.data.warning)
			}
			updateNodeApprovalState(node)
			// TODO
			// reloadTags()
		}
	}).catch((error) => {
		showError(
			t('approval', 'Failed to request approval for {name}', { name: fileName })
			+ ': ' + (error.response?.data?.ocs?.data?.error ?? error.response?.request?.responseText ?? ''),
		)
		console.error(error)
	})
}

export function requestAfterShareCreation(node, ruleId) {
	const fileId = node.fileid
	const fileName = node.basename
	console.debug('requestAfterShareCreation node', node)
	const req = {
		createShares: false,
	}
	const url = generateOcsUrl('apps/approval/api/v1/request/' + fileId + '/' + ruleId, 2)
	axios.post(url, req).then((response) => {
		showSuccess(t('approval', 'Approval requested for {name}', { name: fileName }))
		if (response.data?.ocs?.data?.warning) {
			showWarning(t('approval', 'Warning') + ': ' + response.data.ocs.data.warning)
		}
		updateNodeApprovalState(node)
		// TODO
		// reloadTags()
	}).catch((error) => {
		showError(
			t('approval', 'Failed to request approval for {name}', { name: fileName })
			+ ': ' + (error.response?.data?.ocs?.data?.error ?? error.response?.request?.responseText ?? ''),
		)
	})
}

export function onApproveAction(node) {
	const fileId = node.fileid
	const fileName = node.basename
	const url = generateOcsUrl('apps/approval/api/v1/approve/' + fileId, 2)
	axios.put(url, {}).then((response) => {
		showSuccess(t('approval', 'You approved {name}', { name: fileName }))
		updateNodeApprovalState(node)
	}).catch((error) => {
		console.error(error)
		showError(
			t('approval', 'Failed to approve {name}', { name: fileName })
			+ ': ' + error.response?.request?.responseText,
		)
	})
}

export function onRejectAction(node) {
	const fileId = node.fileid
	const fileName = node.basename
	const url = generateOcsUrl('apps/approval/api/v1/reject/' + fileId, 2)
	axios.put(url, {}).then((response) => {
		showSuccess(t('approval', 'You rejected {name}', { name: fileName }))
		updateNodeApprovalState(node)
	}).catch((error) => {
		console.error(error)
		showError(
			t('approval', 'Failed to reject {name}', { name: fileName })
			+ ': ' + error.response?.request?.responseText,
		)
	})
}

export function getUserRequesterRules(fileId = null) {
	const req = fileId === null
		? null
		: {
			params: {
				fileId,
			},
		}
	const url = generateOcsUrl('apps/approval/api/v1/user-requester-rules', 2)
	return fileId === null
		? axios.get(url)
		: axios.get(url, req)
}

export function onRequestAction(node) {
	const fileId = node.fileid
	OCA.Approval.RequestModalVue.showModal()
	// refresh request rules when opening request modal
	getUserRequesterRules(fileId).then((response) => {
		OCA.Approval.RequestModalVue.setUserRules(response.data.ocs.data)
		OCA.Approval.RequestModalVue.setNode(node)
		OCA.Approval.userRules = response.data.ocs.data
	}).catch((error) => {
		console.error(error)
	})
}

export function openApprovalInfoModal(node) {
	OCA.Approval.InfoModalVue.setNode(node)
	OCA.Approval.InfoModalVue.setUserRules([])
	OCA.Approval.InfoModalVue.showModal()
	getUserRequesterRules(node.fileid).then(response => {
		OCA.Approval.InfoModalVue.setUserRules(response.data.ocs.data)
	})
}
