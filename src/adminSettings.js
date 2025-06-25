/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import AdminSettings from './components/AdminSettings.vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

const app = createApp(AdminSettings)

app.mixin({ methods: { t, n } })
app.mount('#approval_prefs')
