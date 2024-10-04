/**
 * Nextcloud - github
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @copyright Julien Veyssier 2021
 *
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
