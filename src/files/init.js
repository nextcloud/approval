import { registerFileAction, registerDavProperty } from '@nextcloud/files'

import { inlineAction } from './actions/inlineAction.js'
import { requestAction } from './actions/requestAction.js'
import { approveAction } from './actions/approveAction.js'
import { rejectAction } from './actions/rejectAction.js'

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
registerFileAction(requestAction)
registerFileAction(approveAction)
registerFileAction(rejectAction)
