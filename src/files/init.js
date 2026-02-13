/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerFileAction } from '@nextcloud/files'
import { registerDavProperty } from '@nextcloud/files/dav'

import { inlineAction } from './actions/inlineAction.ts'
import { requestAction } from './actions/requestAction.ts'
import { respondAction } from './actions/respondAction.ts'
import { approveAction } from './actions/approveAction.ts'
import { rejectAction } from './actions/rejectAction.ts'

if (!OCA.Approval) {
	OCA.Approval = {
		actionIgnoreLists: [
			'trashbin',
			'files.public',
		],
	}
}

registerDavProperty('nc:approval-state', { nc: 'http://nextcloud.org/ns' })
registerFileAction(inlineAction)
registerFileAction(approveAction)
registerFileAction(rejectAction)
registerFileAction(requestAction)
registerFileAction(respondAction)
