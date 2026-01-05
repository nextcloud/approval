/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import ApprovalSvgIcon from '../img/app-no-color.svg?raw'
import { defineAsyncComponent, defineCustomElement } from 'vue'
import {
	// FileType,
	registerSidebarTab,
} from '@nextcloud/files'
import { isPublicShare } from '@nextcloud/sharing/public'

const tagName = 'approval_sidebar-tab'
const ApprovalTab = defineAsyncComponent(() => import('./views/ApprovalTab.vue'))

registerSidebarTab({
	id: 'approval',
	order: 90,
	displayName: t('approval', 'Approval'),
	iconSvgInline: ApprovalSvgIcon,
	enabled({ node }) {
		if (isPublicShare()) {
			return false
		}
		/*
		if (node.type !== FileType.File) {
			return false
		}
		*/
		// setup tab
		setupTab()
		return true
	},
	tagName,
})

/**
 * Setup the custom element for the Approval sidebar tab.
 */
function setupTab() {
	if (window.customElements.get(tagName)) {
		// already defined
		return
	}

	window.customElements.define(tagName, defineCustomElement(ApprovalTab, {
		shadowRoot: false,
	}))
}
