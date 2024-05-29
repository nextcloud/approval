import FilesRequestModal from '../components/FilesRequestModal.vue'
import InfoModal from '../components/InfoModal.vue'
import { requestApproval, approve, reject, onRequestFileAction } from './helpers.js'
import Vue from 'vue'

export function createFilesRequestModal() {
	const filesRequestModalId = 'filesRequestApprovalModal'
	const filesRequestModalElement = document.createElement('div')
	filesRequestModalElement.id = filesRequestModalId
	document.body.append(filesRequestModalElement)

	const FilesRequestModalView = Vue.extend(FilesRequestModal)
	OCA.Approval.FilesRequestModalVue = new FilesRequestModalView().$mount(filesRequestModalElement)

	OCA.Approval.FilesRequestModalVue.$on('close', () => {
		console.debug('[Approval] modal closed')
	})
	OCA.Approval.FilesRequestModalVue.$on('request', (node, ruleId, createShares) => {
		requestApproval(node.fileid, node.basename, ruleId, createShares, node)
	})
}

export function createInfoModal() {
	const infoModalId = 'approvalInfoModal'
	const infoModalElement = document.createElement('div')
	infoModalElement.id = infoModalId
	document.body.append(infoModalElement)

	const InfoModalView = Vue.extend(InfoModal)
	OCA.Approval.InfoModalVue = new InfoModalView().$mount(infoModalElement)

	OCA.Approval.InfoModalVue.$on('close', () => {
		console.debug('[Approval] modal closed')
	})
	OCA.Approval.InfoModalVue.$on('approve', (node) => {
		approve(node.fileid, node.basename, node)
	})
	OCA.Approval.InfoModalVue.$on('reject', (node) => {
		reject(node.fileid, node.basename, node)
	})
	OCA.Approval.InfoModalVue.$on('request', (node) => {
		onRequestFileAction(node)
	})
}
