import Vue from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

import ApprovalButtons from './components/ApprovalButtons'
import { states } from './states'

/**
 * @class OCA.Approval.ApprovalInfoView
 * @classdesc
 *
 * Displays approval buttons or approval state
 *
 */
export const ApprovalInfoView = OCA.Files.DetailFileInfoView.extend(
	/** @lends OCA.Approval.ApprovalInfoView.prototype */ {

		_rendered: false,
		tagEventsCaugth: false,

		className: 'approvalInfoView',
		name: 'approval',

		/* required by the new files sidebar to check if the view is unique */
		id: 'approvalInfoView',

		_inputView: null,

		fileName: '',
		fileId: 0,
		fileInfo: null,

		initialize(options) {
			options = options || {}
			this.render()
		},

		/**
		 * Renders this details view
		 */
		render() {
			if (this._rendered) {
				return
			}
			// create and mount the component
			const mountPoint = document.createElement('div')
			const View = Vue.extend(ApprovalButtons)
			this._inputView = new View({
				propsData: { state: states.NOTHING },
			}).$mount(mountPoint)
			this.$el.append(this._inputView.$el)

			// listen to approval events
			this._inputView.$on('approve', () => {
				this._onApprove()
			})
			this._inputView.$on('reject', () => {
				this._onReject()
			})

			this.hide()
			this._rendered = true
		},

		_onApprove() {
			const req = {}
			const url = generateUrl('/apps/approval/' + this.fileId + '/approve')
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', '{name} approved!', { name: this.fileName }))
				this.getApprovalStatus()
				// reload system tags after approve
				if (OCA.SystemTags?.View) {
					OCA.SystemTags.View.setFileInfo(this.fileInfo)
				}
			}).catch((error) => {
				showError(
					t('approval', 'Failed to approve {name}', { name: this.fileName })
					+ ': ' + error.response?.request?.responseText
				)
			})
		},

		_onReject() {
			const req = {}
			const url = generateUrl('/apps/approval/' + this.fileId + '/reject')
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', '{name} rejected!', { name: this.fileName }))
				this.getApprovalStatus()
				// reload system tags after reject
				if (OCA.SystemTags?.View) {
					OCA.SystemTags.View.setFileInfo(this.fileInfo)
				}
			}).catch((error) => {
				showError(
					t('approval', 'Failed to reject {name}', { name: this.fileName })
					+ ': ' + error.response?.request?.responseText
				)
			})
		},

		setFileInfo(fileInfo) {
			this.hide()
			// Why is this called twice and fileInfo is not the same on each call?
			this.fileName = fileInfo.name || fileInfo.attributes?.name || ''
			this.fileId = fileInfo.id || fileInfo.attributes?.id || 0
			this.fileInfo = fileInfo

			this.getApprovalStatus()

			// reload approval status when a tag is added or removed
			if (!this.tagEventsCaugth && OCA.SystemTags?.View) {
				this.tagEventsCaugth = true
				OCA.SystemTags.View._inputView.on('select', (tag) => {
					setTimeout(() => {
						this.getApprovalStatus()
					}, 2000)
				}, this)
				OCA.SystemTags.View._inputView.on('deselect', (tag) => {
					setTimeout(() => {
						this.getApprovalStatus()
					}, 2000)
				}, this)
			}
		},

		getApprovalStatus() {
			const url = generateUrl('/apps/approval/' + this.fileId + '/state')
			axios.get(url).then((response) => {
				// i don't know how to change props with Vue instance
				// so it's done with a method changing a data value
				this._inputView.setState(response.data)
				if (response.data !== states.NOTHING) {
					this.show()
				} else {
					this.hide()
				}
			}).catch((error) => {
				showError(
					t('approval', 'Failed to check approval status')
					+ ': ' + error.response?.request?.responseText
				)
			})
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
