/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import CloseCircleSvgIcon from '@mdi/svg/svg/close-circle.svg?raw'
import { states } from '../../states.js'
import { Permission, FileAction } from '@nextcloud/files'
import { reject } from '../helpers.js'

export const rejectAction = new FileAction({
	id: 'approval-reject',
	displayName: ({ nodes }) => {
		return t('approval', 'Reject')
	},
	enabled({ nodes, view }) {
		return !OCA.Approval.actionIgnoreLists.includes(view.id)
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
			&& nodes.some(node => node.attributes['approval-state'] === states.APPROVABLE)
			&& nodes.length > 1
		// && nodes.every(({ type }) => type === FileType.File)
		// && nodes.every(({ mime }) => mime === 'application/some+type')
	},
	iconSvgInline: () => CloseCircleSvgIcon,
	order: 0,
	async exec({ nodes }) {
		const node = nodes[0]
		try {
			await reject(node.fileid, node.basename, node)
		} catch (error) {
			console.debug('Reject action failed')
		}
		return null
	},
	async execBatch({ nodes }) {
		const promises = nodes
			.filter(node => node.attributes['approval-state'] === states.APPROVABLE)
			.map(node => reject(node.fileid, node.basename, node, false))
		const results = await Promise.allSettled(promises)
		return results.map(promise => promise.status === 'fulfilled')
	},
})
