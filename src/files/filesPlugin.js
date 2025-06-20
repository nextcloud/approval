/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createInfoModal, createFilesRequestModal } from './modals.js'
import { getUserRequesterRules } from './helpers.js'

createInfoModal()
createFilesRequestModal()

// on page load: get rules that the current user is able to request with
getUserRequesterRules().then((response) => {
	OCA.Approval.userRules = response.data.ocs.data
}).catch((error) => {
	console.error(error)
})
