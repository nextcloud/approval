import ApprovalSvgIcon from '../../../img/app-dark.svg'
import { Permission, FileAction } from '@nextcloud/files'
import { onRequestAction } from '../helpers.js'

export const requestAction = new FileAction({
	id: 'approval-request',
	displayName: (nodes) => {
		return t('approval', 'Request approval')
	},
	enabled(nodes, view) {
		if (nodes.length !== 1) {
			return false
		}
		return !OCA.Approval.actionIgnoreLists.includes(view.id)
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
			&& OCA.Approval.userRules && OCA.Approval.userRules.length > 0
		// && nodes.every(({ type }) => type === FileType.File)
		// && nodes.every(({ mime }) => mime === 'application/some+type')
	},
	iconSvgInline: () => ApprovalSvgIcon,
	async exec(node) {
		onRequestAction(node)
		return null
	},
})
