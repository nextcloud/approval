import Vue from 'vue'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { showSuccess, showError, showWarning } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

import ApprovalSidebarView from './components/ApprovalSidebarView'
import { states } from './states'

/**
 * @class OCA.Approval.ApprovalInfoView
 * @classdesc
 * Displays approval buttons or approval state
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
		requesterUserId: null,

		fileName: '',
		fileId: 0,
		fileInfo: null,

		userRules: [],

		initialize(options) {
			options = options || {}
			this.render()
			this.getDocusignInfo()
			this.getLibresignInfo()
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
			this._inputView.$on('open-request', () => {
				// refresh request rules when opening request modal
				this.getUserRequesterRules().then((response) => {
					this._inputView.setUserRules(response.data.ocs.data)
					this.userRules = response.data.ocs.data
				}).catch((error) => {
					console.error(error)
				})
			})
			this._inputView.$on('request', (ruleId, createShares) => {
				this._onRequest(ruleId, createShares)
			})
			this._inputView.$on('sign-docusign', () => {
				this._onSignDocusign()
			})
			this._inputView.$on('sign-libresign', () => {
				this._onSignLibresign()
			})

			this._rendered = true
		},

		getUserRequesterRules() {
			const url = generateOcsUrl('apps/approval/api/v1/user-requester-rules', 2)
			return axios.get(url)
		},

		getDocusignInfo() {
			const url = generateUrl('/apps/approval/docusign/info')
			axios.get(url).then((response) => {
				this._inputView.setDocusignConnected(response.data.connected)
				this.docusignConnected = response.data.connected
			}).catch((error) => {
				console.error(error)
			})
		},

		getLibresignInfo() {
			const url = generateUrl('/apps/approval/libresign/info')
			axios.get(url).then((response) => {
				this._inputView.setLibresignEnabled(response.data.enabled)
				this.libresignEnabled = response.data.libresignEnabled
				this.currentUserEmail = response.data.currentUserEmail
			}).catch((error) => {
				console.error(error)
			})
		},

		_onApprove() {
			const req = {}
			const url = generateOcsUrl('apps/approval/api/v1/approve/' + this.fileId, 2)
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', 'You approved {name}', { name: this.fileName }))
				this.getApprovalState(true)
				this.reloadTags()
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
			const url = generateOcsUrl('apps/approval/api/v1/reject/' + this.fileId, 2)
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', 'You rejected {name}', { name: this.fileName }))
				this.getApprovalState(true)
				this.reloadTags()
			}).catch((error) => {
				showError(
					t('approval', 'Failed to reject {name}', { name: this.fileName })
					+ ': ' + error.response?.request?.responseText
				)
			})
		},

		_onRequest(ruleId, createShares) {
			const req = {
				createShares,
			}
			const url = generateOcsUrl('apps/approval/api/v1/request/' + this.fileId + '/' + ruleId, 2)
			axios.post(url, req).then((response) => {
				// TODO make sure we see the freshly created shares
				/*
				if (createShares) {
					const fileList = OCA?.Files?.App?.currentFileList
					fileList?.reload?.() || window.location.reload()
					console.debug(fileList)
					fileList.showDetailsView(this.fileName, 'sharing')
				}
				*/
				if (createShares) {
					this.requestAfterShareCreation(ruleId)
				} else {
					showSuccess(t('approval', 'Approval requested for {name}', { name: this.fileName }))
					if (response.data.ocs.data.warning) {
						showWarning(t('approval', 'Warning') + ': ' + response.data.ocs.data.warning)
					}
					this.reloadTags()
					// if we reload the file item here, it appears twice in file list...
					this.getApprovalState(true)
				}
			}).catch((error) => {
				showError(
					t('approval', 'Failed to request approval for {name}', { name: this.fileName })
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
			}).then(() => {
				if (!createShares) {
					this._inputView.setRequesting(false)
				}
			})
		},

		requestAfterShareCreation(ruleId) {
			const req = {
				createShares: false,
			}
			const url = generateOcsUrl('apps/approval/api/v1/request/' + this.fileId + '/' + ruleId, 2)
			axios.post(url, req).then((response) => {
				showSuccess(t('approval', 'Approval requested for {name}', { name: this.fileName }))
				if (response.data.ocs.data.warning) {
					showWarning(t('approval', 'Warning') + ': ' + response.data.ocs.data.warning)
				}
				this.reloadTags()
				// if we reload the file item here, it appears twice in file list...
				this.getApprovalState(true)
			}).catch((error) => {
				showError(
					t('approval', 'Failed to request approval for {name}', { name: this.fileName })
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
			}).then(() => {
				this._inputView.setRequesting(false)
			})
		},

		_onSignDocusign() {
			const req = {
				requesterUserId: this.requesterUserId,
			}
			const url = generateUrl('/apps/approval/docusign/approval-sign/' + this.fileId)
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', 'You will receive an email from DocuSign to sign the document'))
				if (!this.requesterUserId) {
					showWarning(t('approval', 'The user who requested this approval was not found, remember to send or share the signed document yourself'))
				}
				this._inputView.setDocusignRequested(true)
			}).catch((error) => {
				showError(
					t('approval', 'Failed to request signature with DocuSign')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.error(error)
			})
		},

		_onSignLibresign() {
			const url = generateUrl('/apps/libresign/api/0.1/sign/register')
			const req = {
				file: {
					fileId: this.fileId,
				},
				name: this.fileName.replace(/\.pdf/g, '').replace(/[^a-zA-Z0-9\-_]/g, '-'),
				users: [{
					email: this.currentUserEmail,
					description: t('approval', 'Approval signature'),
					name: getCurrentUser().displayName,
				}],
			}
			axios.post(url, req).then((response) => {
				showSuccess(t('approval', '{name} signature requested via LibreSign', { name: this.fileName }))
				this._inputView.setLibresignEnabled(false)
			}).catch((error) => {
				console.error(error)
				showError(error.response?.data?.message ?? t('approval', 'Impossible to sign this document'))
			}).then(() => {
				this._inputView.setLibresignEnabled(true)
			})
		},

		setFileInfo(fileInfo) {
			// trick to open request modal with file action
			if (OCA.Approval.requestOnFileChange) {
				OCA.Approval.requestOnFileChange = false
				this._inputView.showRequestModal()
			}
			// abort if fileId didn't change
			if (this.fileId === (fileInfo.id || fileInfo.attributes?.id)) {
				return
			}
			// Why is this called twice and fileInfo is not the same on each call?
			this.fileName = fileInfo.name || fileInfo.attributes?.name || ''
			this.fileId = fileInfo.id || fileInfo.attributes?.id || 0
			this.fileInfo = fileInfo

			this._inputView.setIsPdf(fileInfo.mimetype === 'application/pdf')
			this._inputView.setDocusignConnected(this.docusignConnected)
			this._inputView.setDocusignRequested(false)
			this._inputView.setLibresignEnabled(this.libresignEnabled)

			// refresh requester rules info each time we get an approval state
			this.getUserRequesterRules().then((response) => {
				this._inputView.setUserRules(response.data.ocs.data)
				this.userRules = response.data.ocs.data
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.getApprovalState(true)
			})

			// reload approval status when a tag is added or removed
			if (!this.tagEventsCaugth && OCA.SystemTags?.View) {
				this.tagEventsCaugth = true
				OCA.SystemTags.View._inputView.on('select', (tag) => {
					setTimeout(() => {
						this.getApprovalState(true)
					}, 2000)
				}, this)
				OCA.SystemTags.View._inputView.on('deselect', (tag) => {
					setTimeout(() => {
						this.getApprovalState(true)
					}, 2000)
				}, this)
			}
		},

		getApprovalState(reloadFileItem) {
			// get state and details
			const url = generateOcsUrl('apps/approval/api/v1/state/' + this.fileId, 2)
			axios.get(url).then((response) => {
				if (reloadFileItem && this.state !== response.data.ocs.data.state) {
					this.updateFileItem(response.data.ocs.data.state)
				}

				this.state = response.data.ocs.data.state
				if (this.state !== states.NOTHING) {
					// i don't know how to change props with Vue instance
					// so it's done with a method changing a data value
					this._inputView.setState(this.state)
					if (response.data.ocs.data.rule) {
						this._inputView.setRule(response.data.ocs.data.rule)
					} else {
						this._inputView.setRule(null)
					}
					if (response.data.ocs.data.userId && response.data.ocs.data.userName) {
						this._inputView.setUserId(response.data.ocs.data.userId ?? '')
						this.requesterUserId = response.data.ocs.data.userId ?? ''
						this._inputView.setUserName(response.data.ocs.data.userName ?? '')
					} else {
						this._inputView.setUserId('')
						this.requesterUserId = null
						this._inputView.setUserName('')
					}
					if (response.data.ocs.data.timestamp) {
						this._inputView.setDatetime(response.data.ocs.data.timestamp ?? '')
					} else {
						this._inputView.setDatetime('')
					}
					this.show()
				} else {
					this._inputView.setState(states.NOTHING)
					this._inputView.setUserId('')
					this.requesterUserId = null
					this._inputView.setUserName('')
					this._inputView.setDatetime('')
					this._inputView.setRule(null)
					if (this.userRules.length === 0) {
						this.hide()
					} else {
						this.show()
					}
				}
			}).catch((error) => {
				showError(
					t('approval', 'Failed to check approval status')
				)
				console.error(error)
				this._inputView.setState(states.NOTHING)
				this._inputView.setUserId('')
				this.requesterUserId = null
				this._inputView.setUserName('')
				this._inputView.setDatetime('')
				this._inputView.setRule(null)
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

		reloadFileList() {
			const fileList = OCA?.Files?.App?.fileList
			fileList?.reload?.() || window.location.reload()
		},
		openSidebarOnFile(path = null, name = null) {
			if (path && name) {
				OCA.Files.Sidebar.open(path + '/' + name)
			} else {
				OCA.Files.Sidebar.open(this.fileInfo.attributes.path + '/' + this.fileInfo.attributes.name)
			}
		},
		updateFileItem(newState) {
			const fileList = OCA?.Files?.App?.currentFileList
			const model = fileList.getModelForFile(this.fileName)
			// this was used when row rendering was getting the state
			// model.trigger('change', model)
			// but now we pass it directly to the model and it re-renders

			// model can be null if we display the sidebar for an element that is not in current directory
			// for example when clicking the share button in the breadcrumb
			if (model) {
				model.set('approvalState', newState)
			}
		},
		reloadTags() {
			if (OCA.SystemTags?.View) {
				OCA.SystemTags.View.setFileInfo(this.fileInfo)
			}
		},
	})
