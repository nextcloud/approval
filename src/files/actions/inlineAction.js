/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import PendingIconSvg from '@mdi/svg/svg/dots-horizontal-circle-outline.svg?raw'
import ApprovedIconSvg from '../../../img/checkmark-green.svg?raw'
import RejectedIconSvg from '../../../img/close-red.svg?raw'
import { Permission, FileAction } from '@nextcloud/files'
import { states } from '../../states.js'
import { openApprovalInfoModal, updateNodeApprovalState } from '../helpers.js'

export const inlineAction = new FileAction({
	id: 'approval-inline',
	title: ({ nodes }) => {
		if (nodes.length !== 1) {
			return ''
		}
		const node = nodes[0]
		const state = node.attributes['approval-state']
		return state === states.PENDING
			? t('approval', 'Waiting for authorized users to approve this file')
			: state === states.APPROVABLE
				? t('approval', 'Pending approval, click to approve/reject')
				: state === states.APPROVED
					? t('approval', 'This element was approved')
					: t('approval', 'This element was rejected')
	},
	displayName: ({ nodes }) => {
		if (nodes.length !== 1) {
			return ''
		}
		const node = nodes[0]

		const state = node.attributes['approval-state']
		return state === states.PENDING
			? t('approval', 'Pending approval')
			: state === states.APPROVABLE
				? t('approval', 'Waiting for your approval')
				: state === states.APPROVED
					? t('approval', 'Approved')
					: t('approval', 'Rejected')
	},
	inline: () => true,
	exec: async ({ nodes }) => {
		const node = nodes[0]
		await updateNodeApprovalState(node)
		await openApprovalInfoModal(node)
		return null
	},
	order: -10,
	iconSvgInline({ nodes }) {
		const node = nodes[0]

		const state = node.attributes['approval-state']
		return state === states.PENDING || state === states.APPROVABLE
			? PendingIconSvg
			: state === states.APPROVED
				? ApprovedIconSvg
				: RejectedIconSvg
	},
	enabled({ nodes }) {
		// Only works on single node
		if (nodes.length !== 1) {
			return false
		}

		const node = nodes[0]
		const state = node.attributes['approval-state']

		return (node.permissions & Permission.READ) !== 0
			&& [states.PENDING, states.APPROVABLE, states.APPROVED, states.REJECTED].includes(state)
	},
})
