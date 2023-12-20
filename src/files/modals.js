import RequestModal from '../components/RequestModal.vue'
import InfoModal from '../components/InfoModal.vue'
import { requestApproval, onApproveAction, onRejectAction, onRequestAction } from './helpers.js'
import Vue from 'vue'

export function createRequestModal() {
	const requestModalId = 'requestApprovalModal'
	const requestModalElement = document.createElement('div')
	requestModalElement.id = requestModalId
	document.body.append(requestModalElement)

	const RequestModalView = Vue.extend(RequestModal)
	OCA.Approval.RequestModalVue = new RequestModalView().$mount(requestModalElement)

	OCA.Approval.RequestModalVue.$on('close', () => {
		console.debug('[Approval] modal closed')
	})
	OCA.Approval.RequestModalVue.$on('request', (node, ruleId, createShares) => {
		requestApproval(node, ruleId, createShares)
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
		onApproveAction(node)
	})
	OCA.Approval.InfoModalVue.$on('reject', (node) => {
		onRejectAction(node)
	})
	OCA.Approval.InfoModalVue.$on('request', (node) => {
		onRequestAction(node)
	})
}
