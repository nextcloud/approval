/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import CheckCircleSvgIcon from '@mdi/svg/svg/check-circle.svg?raw'
import { Permission } from '@nextcloud/files'
import type { IFileAction } from '@nextcloud/files'
import { states } from '../../states.js'
import { approve, abortIfAlreadyVoted } from '../helpers.js'

export const approveAction: IFileAction = {
	id: 'approval-approve',
	displayName: () => {
		return t('approval', 'Approve')
	},
	enabled({ nodes, view }) {
		return !OCA.Approval.actionIgnoreLists.includes(view.id)
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
			&& nodes.some(node => node.attributes['approval-state'] === states.APPROVABLE)
			&& nodes.length > 1
		// && nodes.every(({ type }) => type === FileType.File)
		// && nodes.every(({ mime }) => mime === 'application/some+type')
	},
	iconSvgInline: () => CheckCircleSvgIcon,
	order: 0,
	async exec({ nodes }) {
		const node = nodes[0]
		try {
			// enabled()/title() stay APPROVABLE-driven until the state is
			// refetched; the server 400 is the backstop, this guard aborts
			// as soon as the freshly fetched state shows an existing vote
			if (await abortIfAlreadyVoted(node)) {
				return null
			}
			await approve(node)
		} catch (error) {
			console.debug('Approve action failed')
		}
		return null
	},
	async execBatch({ nodes }) {
		const promises = nodes
			.filter(node => node.attributes['approval-state'] === states.APPROVABLE)
			.map(async (node) => {
				if (await abortIfAlreadyVoted(node)) {
					// nothing was decided on this node: report it as failed
					// instead of counting the skipped vote as a success
					throw new Error('Already voted')
				}
				await approve(node, false)
			})
		const results = await Promise.allSettled(promises)
		return results.map(promise => promise.status === 'fulfilled')
	},
}
