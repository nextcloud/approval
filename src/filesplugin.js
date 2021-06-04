/*
 * Copyright (c) 2021 Julien Veyssier <eneiluj@posteo.net>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */
import { ApprovalInfoView } from './approvalinfoview'
import DocuSignModal from './components/DocuSignModal'
import { states } from './states'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'

import Vue from 'vue'
import './bootstrap'

(function() {
	if (!OCA.Approval) {
		/**
		 * @namespace
		 */
		OCA.Approval = {
			requestOnFileChange: false,
		}
	}

	OC.Files.Client.PROPERTY_APPROVAL_STATE = '{' + OC.Files.Client.NS_NEXTCLOUD + '}approval-state'

	/**
	 * @namespace
	 */
	OCA.Approval.FilesPlugin = {
		ignoreLists: [
			'trashbin',
			'files.public',
		],

		attach(fileList) {
			if (this.ignoreLists.indexOf(fileList.id) >= 0) {
				return
			}

			const approvalInfoView = new ApprovalInfoView()
			fileList.registerDetailView(approvalInfoView)
			OCA.Approval.View = approvalInfoView

			const oldGetWebdavProperties = fileList._getWebdavProperties
			fileList._getWebdavProperties = function() {
				const props = oldGetWebdavProperties.apply(this, arguments)
				props.push(OC.Files.Client.PROPERTY_APPROVAL_STATE)
				return props
			}

			fileList.filesClient.addFileInfoParser(function(response) {
				const data = {}
				const props = response.propStat[0].properties
				const approvalState = props[OC.Files.Client.PROPERTY_APPROVAL_STATE]
				if (approvalState !== undefined) {
					data.approvalState = approvalState
				}
				return data
			})

			const oldCreateRow = fileList._createRow
			fileList._createRow = function(fileData) {
				const $tr = oldCreateRow.apply(this, arguments)
				$tr.attr('data-approval-state', fileData.approvalState)
				return $tr
			}

			fileList.fileActions.registerAction({
				name: 'ApprovalStateInline',
				render: (actionSpec, isDefault, context) => {
					// just in case, remove existing
					const existingActionLink = context.$file.find('.approval-inline-state')
					if (existingActionLink) {
						existingActionLink.remove()
					}

					const state = context.$file.data('approvalState')
					if (state !== states.NOTHING) {
						const actionLink = document.createElement('span')
						actionLink.classList.add('approval-inline-state')
						if (state === states.APPROVED) {
							actionLink.classList.add('icon-approved')
							actionLink.classList.add('icon-checkmark-white')
						} else if (state === states.REJECTED) {
							actionLink.classList.add('icon-rejected')
							actionLink.classList.add('icon-close-white')
						} else if (state === states.PENDING) {
							actionLink.classList.add('icon-pending')
							actionLink.classList.add('icon-more-white')
						} else if (state === states.APPROVABLE) {
							actionLink.classList.add('icon-approvable')
							actionLink.classList.add('icon-more-white')
							const tw = context.$file.find('a.name>.thumbnail-wrapper')
							if (tw.length > 0) {
								tw[0].setAttribute('title', t('approval', 'Pending approval, you are authorized to approve.'))
							}
						}
						context.$file.find('a.name>.thumbnail-wrapper').append(actionLink)
						return actionLink
					}
					return null
				},
				mime: 'all',
				order: -140,
				type: OCA.Files.FileActions.TYPE_INLINE,
				permissions: OC.PERMISSION_READ,
				actionHandler: null,
			})

			fileList.fileActions.registerAction({
				name: 'approval-approve',
				displayName: (context) => {
					if (context && context.$file) {
						const state = context.$file.data('approvalState')
						if (state === states.APPROVABLE) {
							return t('approval', 'Approve')
						}
					}
					return ''
				},
				mime: 'all',
				order: -139,
				iconClass: (fileName, context) => {
					if (context && context.$file) {
						const state = context.$file.data('approvalState')
						if (state === states.APPROVABLE) {
							return 'icon-checkmark'
						}
					}
				},
				permissions: OC.PERMISSION_READ,
				actionHandler: this.approve,
			})

			fileList.fileActions.registerAction({
				name: 'approval-reject',
				displayName: (context) => {
					if (context && context.$file) {
						const state = context.$file.data('approvalState')
						if (state === states.APPROVABLE) {
							return t('approval', 'Reject')
						}
					}
					return ''
				},
				mime: 'all',
				order: -139,
				iconClass: (fileName, context) => {
					if (context && context.$file) {
						const state = context.$file.data('approvalState')
						if (state === states.APPROVABLE) {
							return 'icon-close'
						}
					}
				},
				permissions: OC.PERMISSION_READ,
				actionHandler: this.reject,
			})

			fileList.fileActions.registerAction({
				name: 'approval-request',
				displayName: (context) => {
					if (context && context.$file) {
						const state = context.$file.data('approvalState')
						if (state === states.NOTHING && OCA.Approval.userRules && OCA.Approval.userRules.length > 0) {
							return t('approval', 'Request approval')
						}
					}
					return ''
				},
				mime: 'all',
				order: -139,
				iconClass: (fileName, context) => {
					if (context && context.$file) {
						const state = context.$file.data('approvalState')
						if (state === states.NOTHING && OCA.Approval.userRules && OCA.Approval.userRules.length > 0) {
							return 'icon-approval'
						}
					}
				},
				permissions: OC.PERMISSION_READ,
				actionHandler: this.request,
			})

			fileList.fileActions.registerAction({
				name: 'approval-pending-info',
				displayName: (context) => {
					if (context && context.$file) {
						const state = context.$file.data('approvalState')
						if (state === states.PENDING) {
							return t('approval', 'Show approval details')
						}
					}
					return ''
				},
				mime: 'all',
				order: -139,
				iconClass: (fileName, context) => {
					if (context && context.$file) {
						const state = context.$file.data('approvalState')
						if (state === states.PENDING) {
							return 'icon-approval'
						}
					}
				},
				permissions: OC.PERMISSION_READ,
				actionHandler: this.showPendingInfo,
			})

			fileList.fileActions.registerAction({
				name: 'approval-sign-docusign',
				displayName: (context) => {
					if (context && context.$file && OCA.Approval.docusignConnected) {
						return t('approval', 'Request signature')
					}
					return ''
				},
				mime: 'application/pdf',
				order: -139,
				iconClass: (fileName, context) => {
					if (context && context.$file && OCA.Approval.docusignConnected) {
						return 'icon-rename'
					}
				},
				permissions: OC.PERMISSION_READ,
				actionHandler: this.signDocuSign,
			})
		},

		approve: (fileName, context) => {
			const fileId = context.$file.data('id')
			// const state = context.$file.data('approvalState')
			const model = context.fileList.getModelForFile(fileName)

			const req = {}
			const url = generateUrl('/apps/approval/' + fileId + '/approve')
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', '{name} approved!', { name: fileName }))
				model.set('approvalState', states.APPROVED)
				OCA.Approval.View.getApprovalState(false)
				OCA.Approval.View.reloadTags()
			}).catch((error) => {
				console.error(error)
				showError(
					t('approval', 'Failed to approve {name}', { name: fileName })
					+ ': ' + error.response?.request?.responseText
				)
			})
		},

		reject: (fileName, context) => {
			const fileId = context.$file.data('id')
			// const state = context.$file.data('approvalState')
			const model = context.fileList.getModelForFile(fileName)

			const req = {}
			const url = generateUrl('/apps/approval/' + fileId + '/reject')
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', '{name} rejected!', { name: fileName }))
				model.set('approvalState', states.REJECTED)
				OCA.Approval.View.getApprovalState(false)
				OCA.Approval.View.reloadTags()
			}).catch((error) => {
				showError(
					t('approval', 'Failed to reject {name}', { name: fileName })
					+ ': ' + error.response?.request?.responseText
				)
			})
		},

		request: (fileName, context) => {
			OCA.Approval.requestOnFileChange = true
			OCA.Approval.View.openSidebarOnFile(context.dir, fileName)
		},

		signDocuSign: (fileName, context) => {
			const fileId = context.$file.data('id')
			OCA.Approval.DocuSignModalVue.$children[0].setFileId(fileId)
			OCA.Approval.DocuSignModalVue.$children[0].showModal()
		},

		showPendingInfo: (fileName, context) => {
			OCA.Approval.View.openSidebarOnFile(context.dir, fileName)
		},
	}

})()

OC.Plugins.register('OCA.Files.FileList', OCA.Approval.FilesPlugin)

// signature modal
const modalId = 'docusignModal'
const modalElement = document.createElement('div')
modalElement.id = modalId
document.body.append(modalElement)

OCA.Approval.DocuSignModalVue = new Vue({
	el: modalElement,
	render: h => {
		return h(DocuSignModal)
	},
})

// is DocuSign configured?
const urlDs = generateUrl('/apps/approval/docusign/info')
axios.get(urlDs).then((response) => {
	OCA.Approval.docusignConnected = response.data.connected
}).catch((error) => {
	console.error(error)
})

// on page load: get rules with current user as able to request
const url = generateUrl('/apps/approval/user-requester-rules')
axios.get(url).then((response) => {
	OCA.Approval.userRules = response.data
}).catch((error) => {
	console.error(error)
})
