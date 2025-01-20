/* jshint esversion: 6 */

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import './bootstrap.js'
import DashboardPending from './views/DashboardPending.vue'

document.addEventListener('DOMContentLoaded', function() {

	OCA.Dashboard.register('approval_pending', (el, { widget }) => {
		const View = Vue.extend(DashboardPending)
		new View({
			propsData: { title: widget.title },
		}).$mount(el)
	})

})
