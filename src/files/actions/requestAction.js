/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import CheckSvgIcon from '@mdi/svg/svg/check.svg?raw'
import { Permission, FileAction } from '@nextcloud/files'
import { onRequestFileAction } from '../helpers.js'

export const requestAction = new FileAction({
	id: 'approval-request',
	displayName: (nodes) => {
		return t('approval', 'Request approval')
	},
	enabled({ nodes, view }) {
		if (nodes.length !== 1) {
			return false
		}
		return !OCA.Approval.actionIgnoreLists.includes(view.id)
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
			&& OCA.Approval.userRules && OCA.Approval.userRules.length > 0
		// && nodes.every(({ type }) => type === FileType.File)
		// && nodes.every(({ mime }) => mime === 'application/some+type')
	},
	iconSvgInline: () => CheckSvgIcon,
	order: 1,
	async exec({ nodes }) {
		await onRequestFileAction(nodes[0])
		return null
	},
})
