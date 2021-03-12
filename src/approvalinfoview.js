import Vue from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

import ApprovalButtons from './components/ApprovalButtons'

/**
 * @class OCA.Approval.ApprovalInfoView
 * @classdesc
 *
 * Displays a approval buttons
 *
 */
export const ApprovalInfoView = OCA.Files.DetailFileInfoView.extend(
	/** @lends OCA.Approval.ApprovalInfoView.prototype */ {

		_rendered: false,

		className: 'approvalInfoView',
		name: 'approval',

		/* required by the new files sidebar to check if the view is unique */
		id: 'approvalInfoView',

		_inputView: null,

		fileName: '',
		fileId: 0,

		initialize(options) {
			options = options || {}
		},

		_onApprove() {
			console.debug('!!!!!!! Approve ' + this.fileName)
			const req = {}
			const url = generateUrl('/apps/approval/' + this.fileId + '/approve')
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', '{name} approved!', { name: this.fileName }))
			}).catch((error) => {
				showError(
					t('approval', 'Failed to approve {name}', { name: this.fileName })
					+ ': ' + error.response?.request?.responseText
				)
			})
		},

		_onReject() {
			console.debug('!!!!!!! Reject ' + this.fileName)
			const req = {}
			const url = generateUrl('/apps/approval/' + this.fileId + '/disapprove')
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', '{name} disapproved!', { name: this.fileName }))
			}).catch((error) => {
				showError(
					t('approval', 'Failed to disapprove {name}', { name: this.fileName })
					+ ': ' + error.response?.request?.responseText
				)
			})
		},

		setFileInfo(fileInfo) {
			console.debug('setFileInfo')
			console.debug(fileInfo)
			// Why is this called twice and fileInfo is not the same on each call?
			this.fileName = fileInfo.name || fileInfo.attributes?.name || ''
			this.fileId = fileInfo.id || fileInfo.attributes?.id || 0

			if (!this._rendered) {
				this.render()
			}
		},

		/**
		 * Renders this details view
		 */
		render() {
			console.debug('RENDER')
			// create and mount the component
			const mountPoint = document.createElement('div')
			const View = Vue.extend(ApprovalButtons)
			this._inputView = new View({
				propsData: { },
			}).$mount(mountPoint)
			this.$el.append(this._inputView.$el)

			// listen to approval events
			this._inputView.$on('yes', () => {
				this._onApprove()
			})
			this._inputView.$on('no', () => {
				this._onReject()
			})

			this._rendered = true
		},

		isVisible() {
			return !this.$el.hasClass('hidden')
		},

		show() {
			this.$el.removeClass('hidden')
		},

		hide() {
			this.$el.addClass('hidden')
		},

		toggle() {
			this.$el.toggleClass('hidden')
		},

		remove() {
			this._inputView.remove()
		},
	})
