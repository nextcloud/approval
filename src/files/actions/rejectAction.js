import CloseCircleSvgIcon from '@mdi/svg/svg/close-circle.svg'
import { states } from '../../states.js'
import { Permission, FileAction } from '@nextcloud/files'
import { onRejectAction } from '../helpers.js'

export const rejectAction = new FileAction({
	id: 'approval-reject',
	displayName: (nodes) => {
		return t('approval', 'Reject')
	},
	enabled(nodes, view) {
		return !OCA.Approval.actionIgnoreLists.includes(view.id)
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
			&& nodes.some(node => node.attributes['approval-state'] === states.APPROVABLE)
		// && nodes.every(({ type }) => type === FileType.File)
		// && nodes.every(({ mime }) => mime === 'application/some+type')
	},
	iconSvgInline: () => CloseCircleSvgIcon,
	order: 0,
	async exec(node) {
		try {
			await onRejectAction(node)
		} catch (error) {
			console.debug('Reject action failed')
		}
		return null
	},
	async execBatch(nodes) {
		const promises = nodes
			.filter(node => node.attributes['approval-state'] === states.APPROVABLE)
			.map(onRejectAction)
		const results = await Promise.allSettled(promises)
		return results.map(promise => promise.status === 'fulfilled')
	},
})
