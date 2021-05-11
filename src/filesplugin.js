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
import { states } from './states'

(function() {
	if (!OCA.Approval) {
		/**
		 * @namespace
		 */
		OCA.Approval = {}
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
				console.debug('_getWebdavProperties')
				console.debug(props)
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
				if (fileData.approvalState !== states.NOTHING) {
					// $tr.attr('data-lock-owner', fileData.lockOwner)
				}
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

					console.debug('stststsstststs')
					console.debug(context.$file.data('approvalState'))

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
							actionLink.classList.add('icon-checkmark-white')
						} else if (state === states.APPROVABLE) {
							actionLink.classList.add('icon-approvable')
							actionLink.classList.add('icon-checkmark-white')
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
		},
	}

})()

OC.Plugins.register('OCA.Files.FileList', OCA.Approval.FilesPlugin)
