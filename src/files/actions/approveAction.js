import CheckCircleSvgIcon from '@mdi/svg/svg/check-circle.svg'
import { Permission, FileAction } from '@nextcloud/files'
import { states } from '../../states.js'
import { approve } from '../helpers.js'

export const approveAction = new FileAction({
	id: 'approval-approve',
	displayName: (nodes) => {
		return t('approval', 'Approve')
	},
	enabled(nodes, view) {
		return !OCA.Approval.actionIgnoreLists.includes(view.id)
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
			&& nodes.some(node => node.attributes['approval-state'] === states.APPROVABLE)
		// && nodes.every(({ type }) => type === FileType.File)
		// && nodes.every(({ mime }) => mime === 'application/some+type')
	},
	iconSvgInline: () => CheckCircleSvgIcon,
	order: 0,
	async exec(node) {
		try {
			await approve(node.fileid, node.basename, node)
		} catch (error) {
			console.debug('Approve action failed')
		}
		return null
	},
	async execBatch(nodes) {
		const promises = nodes
			.filter(node => node.attributes['approval-state'] === states.APPROVABLE)
			.map(node => approve(node.fileid, node.basename, node, false))
		const results = await Promise.allSettled(promises)
		return results.map(promise => promise.status === 'fulfilled')
	},
})
