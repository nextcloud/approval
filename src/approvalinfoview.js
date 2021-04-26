import Vue from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

import ApprovalSidebarView from './components/ApprovalSidebarView'
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
		state: states.NOTHING,

		fileName: '',
		fileId: 0,
		fileInfo: null,

		initialize(options) {
			options = options || {}
			this.render()
			this.getUserRules()
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
			const View = Vue.extend(ApprovalSidebarView)
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
				this.updateFileItem()
			}).catch((error) => {
				console.error(error)
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
				this.updateFileItem()
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

			// reset component details
			this._inputView.setUserId('')
			this._inputView.setUserName('')
			this._inputView.setDatetime('')

			this.getApprovalStatus()

			// reload approval status when a tag is added or removed
			if (!this.tagEventsCaugth && OCA.SystemTags?.View) {
				this.tagEventsCaugth = true
				OCA.SystemTags.View._inputView.on('select', (tag) => {
					setTimeout(() => {
						this.getApprovalStatus(true)
					}, 2000)
				}, this)
				OCA.SystemTags.View._inputView.on('deselect', (tag) => {
					setTimeout(() => {
						this.getApprovalStatus(true)
					}, 2000)
				}, this)
			}
		},

		getApprovalStatus(reloadFileItem) {
			const url = generateUrl('/apps/approval/' + this.fileId + '/state')
			axios.get(url).then((response) => {
				if (reloadFileItem && this.state !== response.data) {
					this.updateFileItem()
				}

				this.state = response.data
				if (response.data !== states.NOTHING) {
					this.getDetails()
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

		getUserRules() {
			const url = generateUrl('/apps/approval/user-rules')
			axios.get(url).then((response) => {
				console.debug('rules are')
				console.debug(response.data)
				this._inputView.setUserRules(response.data)
			}).catch((error) => {
				console.error(error)
			})
		},

		/**
		 * Get who and when
		 */
		async getDetails() {
			const limit = 50
			let since = 0
			let response
			do {
				// eslint-disable-next-line
				const params = new URLSearchParams()
				params.append('format', 'json')
				params.append('limit', limit)
				if (since > 0) {
					params.append('since', since)
				}
				try {
					response = await axios.get(generateOcsUrl('apps/activity/api/v2/activity') + '/approval' + '?' + params)
					const activities = response.data.ocs.data
					since = activities.length
						? activities[activities.length - 1].activity_id
						: 0
					const lastActivity = activities.find((a) => {
						return a.object_id === this.fileId
					})
					if (lastActivity) {
						this._inputView.setUserId(lastActivity.subject_rich[1].user.id)
						this._inputView.setUserName(lastActivity.subject_rich[1].user.name)
						this._inputView.setDatetime(lastActivity.datetime)
						this._inputView.setState(this.state)
						this.show()
						return
					}
				} catch (error) {
					console.error(error)
				}
			} while (response.data.ocs.data.length === limit)

			this._inputView.setUserId('')
			this._inputView.setUserName('')
			this._inputView.setDatetime('')
			// i don't know how to change props with Vue instance
			// so it's done with a method changing a data value
			this._inputView.setState(this.state)
			this.show()
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

		reloadFileList() {
			const fileList = OCA?.Files?.App?.fileList
			fileList?.reload?.() || window.location.reload()
		},
		openSidebarOnFile() {
			OCA.Files.Sidebar.open(this.fileInfo.attributes.path + '/' + this.fileInfo.attributes.name)
		},
		updateFileItem() {
			const model = OCA.Files.App.fileList.getModelForFile(this.fileName)
			model.trigger('change', model)
		},
	})
