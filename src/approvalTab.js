/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import ApprovalSvgIcon from '../img/app-no-color.svg?raw'
import { createApp } from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import ApprovalTab from './views/ApprovalTab.vue'

// Init approval tab component
let TabInstance = null
const approvalTab = new OCA.Files.Sidebar.Tab({
	id: 'approval',
	name: t('approval', 'Approval'),
	iconSvg: ApprovalSvgIcon,

	async mount(el, fileInfo, context) {
		if (TabInstance) {
			TabInstance.unmount()
		}
		const Tab = createApp(ApprovalTab)
		Tab.mixin({ methods: { t, n } })

		TabInstance = Tab.mount(el)
		// Only mount after we have all the info we need
		await TabInstance.update(fileInfo)
	},

	update(fileInfo) {
		if (TabInstance && typeof TabInstance.update === 'function') {
			TabInstance.update(fileInfo)
		}
	},

	destroy() {
		if (TabInstance) {
			TabInstance.unmount()
			TabInstance = null
		}
	},
})

window.addEventListener('DOMContentLoaded', () => {
	if (OCA.Files && OCA.Files.Sidebar) {
		OCA.Files.Sidebar.registerTab(approvalTab)
	}
})
