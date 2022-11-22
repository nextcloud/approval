/*
 * Copyright (c) 2021 Julien Veyssier <eneiluj@posteo.net>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */
import { ApprovalInfoView } from './approvalinfoview.js'
import DocuSignModal from './components/DocuSignModal.vue'
import { states } from './states.js'

import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'

import Vue from 'vue'
import './bootstrap.js'

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
					if (state !== undefined && state !== states.NOTHING) {
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

			fileList.registerMultiSelectFileAction({
				name: 'approveMulti',
				displayName: t('approval', 'Approve'),
				iconClass: 'icon-checkmark',
				order: -2,
				action: (selectedFiles) => { this.approveMulti(selectedFiles, fileList, this) },
			})

			fileList.registerMultiSelectFileAction({
				name: 'rejectMulti',
				displayName: t('approval', 'Reject'),
				iconClass: 'icon-close',
				order: -1,
				action: (selectedFiles) => { this.rejectMulti(selectedFiles, fileList, this) },
			})

			// when the multiselect menu is opened =>
			// only show accept/reject if at least one selected item is approvable
			fileList.$el.find('.actions-selected').click(() => {
				let showMultiApproveReject = false
				for (const fid in fileList._selectedFiles) {
					const file = fileList.files.find((t) => parseInt(fid) === t.id)
					if (file && file.approvalState && parseInt(file.approvalState) === states.APPROVABLE) {
						showMultiApproveReject = true
						break
					}
				}
				fileList.fileMultiSelectMenu.toggleItemVisibility('approveMulti', showMultiApproveReject)
				fileList.fileMultiSelectMenu.toggleItemVisibility('rejectMulti', showMultiApproveReject)
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
				actionHandler: (fileName, context) => { this.approveSingle(fileName, context, this) },
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
				actionHandler: (fileName, context) => { this.rejectSingle(fileName, context, this) },
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

		approveMulti: (selectedFiles, fileList, that) => {
			selectedFiles.forEach((f) => {
				// why does this model miss the approvalState?
				// const model = fileList.getModelForFile(f.name)
				// trick to get the approval state...
				const file = fileList.files.find((t) => f.id === t.id)
				if (parseInt(file.approvalState) === states.APPROVABLE) {
					that.approve(file.id, file.name, fileList)
				}
			})
		},

		rejectMulti: (selectedFiles, fileList, that) => {
			selectedFiles.forEach((f) => {
				// why does this model miss the approvalState?
				// const model = fileList.getModelForFile(f.name)
				// trick to get the approval state...
				const file = fileList.files.find((t) => f.id === t.id)
				if (parseInt(file.approvalState) === states.APPROVABLE) {
					that.reject(file.id, file.name, fileList)
				}
			})
		},

		approveSingle: (fileName, context, that) => {
			const fileId = context.$file.data('id')
			that.approve(fileId, fileName, context.fileList)
		},

		rejectSingle: (fileName, context, that) => {
			const fileId = context.$file.data('id')
			that.reject(fileId, fileName, context.fileList)
		},

		approve: (fileId, fileName, fileList) => {
			const url = generateOcsUrl('apps/approval/api/v1/approve/' + fileId, 2)
			axios.put(url, {}).then((response) => {
				showSuccess(t('approval', 'You approved {name}', { name: fileName }))
				const model = fileList.getModelForFile(fileName)
				model.set('approvalState', states.APPROVED)
				// sidebar shows this file info => reload tags and approval state
				if (OCA.Approval.View.fileId === fileId) {
					OCA.Approval.View.getApprovalState(false)
					OCA.Approval.View.reloadTags()
				}
			}).catch((error) => {
				console.error(error)
				showError(
					t('approval', 'Failed to approve {name}', { name: fileName })
					+ ': ' + error.response?.request?.responseText
				)
			})
		},

		reject: (fileId, fileName, fileList) => {
			const url = generateOcsUrl('apps/approval/api/v1/reject/' + fileId, 2)
			axios.put(url, {}).then((response) => {
				showSuccess(t('approval', 'You rejected {name}', { name: fileName }))
				const model = fileList.getModelForFile(fileName)
				model.set('approvalState', states.REJECTED)
				// sidebar shows this file info => reload tags and approval state
				if (OCA.Approval.View.fileId === fileId) {
					OCA.Approval.View.getApprovalState(false)
					OCA.Approval.View.reloadTags()
				}
			}).catch((error) => {
				console.error(error)
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
const url = generateOcsUrl('apps/approval/api/v1/user-requester-rules', 2)
axios.get(url).then((response) => {
	OCA.Approval.userRules = response.data.ocs.data
}).catch((error) => {
	console.error(error)
})
