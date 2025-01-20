/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import './bootstrap.js'
import AdminSettings from './components/AdminSettings.vue'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'
Vue.directive('tooltip', Tooltip)

const View = Vue.extend(AdminSettings)
new View().$mount('#approval_prefs')
