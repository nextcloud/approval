/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import DashboardPending from './views/DashboardPending.vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

const app = createApp(DashboardPending)

app.mixin({ methods: { t, n } })
app.mount('#approval_prefs')

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('approval_pending', (el, { widget }) => {
		const app = createApp(DashboardPending, {
			title: widget.title,
		})
		app.mount(el)
	})
})
