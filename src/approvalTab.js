/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import ApprovalSvgIcon from '../img/app-no-color.svg'
import { getRequestToken } from '@nextcloud/auth'
import Vue from 'vue'
import ApprovalTab from './views/ApprovalTab.vue'

__webpack_nonce__ = btoa(getRequestToken()) // eslint-disable-line
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(ApprovalTab)
// Init approval tab component
let TabInstance = null
const approvalTab = new OCA.Files.Sidebar.Tab({
	id: 'approval',
	name: t('approval', 'Approval'),
	iconSvg: ApprovalSvgIcon,

	async mount(el, fileInfo, context) {
		if (TabInstance) {
			TabInstance.$destroy()
		}
		TabInstance = new View({
			// Better integration with vue parent component
			parent: context,
		})
		// Only mount after we have all the info we need
		await TabInstance.update(fileInfo)
		TabInstance.$mount(el)
	},
	update(fileInfo) {
		TabInstance.update(fileInfo)
	},
	destroy() {
		TabInstance.$destroy()
		TabInstance = null
	},
})

window.addEventListener('DOMContentLoaded', () => {
	if (OCA.Files && OCA.Files.Sidebar) {
		OCA.Files.Sidebar.registerTab(approvalTab)
	}
})
