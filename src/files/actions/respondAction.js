/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import PendingIconSvg from '@mdi/svg/svg/dots-horizontal-circle-outline.svg?raw'
import { Permission, FileAction } from '@nextcloud/files'
import { states } from '../../states.js'
import { openApprovalInfoModal, updateNodeApprovalState } from '../helpers.js'

export const respondAction = new FileAction({
	id: 'approval-respond',
	displayName: ({ nodes }) => {
		return t('approval', 'Approve or Reject')
	},
	enabled({ nodes, view }) {
		return !OCA.Approval.actionIgnoreLists.includes(view.id)
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
			&& nodes.some(node => node.attributes['approval-state'] === states.APPROVABLE)
			&& nodes.length === 1
		// && nodes.every(({ type }) => type === FileType.File)
		// && nodes.every(({ mime }) => mime === 'application/some+type')
	},
	iconSvgInline: () => PendingIconSvg,
	order: 0,
	async exec({ nodes }) {
		const node = nodes[0]
		try {
			await updateNodeApprovalState(node)
			await openApprovalInfoModal(node)
		} catch (error) {
			console.debug('Approve or Reject action failed')
		}
		return null
	},
	async execBatch(nodes) {
	},
})
