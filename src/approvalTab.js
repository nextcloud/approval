/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import ApprovalSvgIcon from '../img/app-no-color.svg?raw'
import { createApp } from 'vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import ApprovalTab from './views/ApprovalTab.vue'

// Init approval tab component
let tabView = null
let tabApp = null
const approvalTab = new OCA.Files.Sidebar.Tab({
	id: 'approval',
	name: t('approval', 'Approval'),
	iconSvg: ApprovalSvgIcon,

	async mount(el, fileInfo, context) {
		if (tabApp !== null) {
			tabApp.unmount()
		}
		tabApp = createApp(ApprovalTab)
		tabApp.mixin({ methods: { t, n } })

		tabView = tabApp.mount(el)
		// Only mount after we have all the info we need
		await tabView.update(fileInfo)
	},

	update(fileInfo) {
		if (tabView && typeof tabView.update === 'function') {
			tabView.update(fileInfo)
		}
	},

	destroy() {
		if (tabApp) {
			tabApp.unmount()
			tabView = null
			tabApp = null
		}
	},
})

window.addEventListener('DOMContentLoaded', () => {
	if (OCA.Files && OCA.Files.Sidebar) {
		OCA.Files.Sidebar.registerTab(approvalTab)
	}
})
