/*
 * Copyright (c) 2023 Julien Veyssier <julien-nc@posteo.net>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */
import { createInfoModal, createFilesRequestModal } from './modals.js'
import { getUserRequesterRules } from './helpers.js'
import '../bootstrap.js'

createInfoModal()
createFilesRequestModal()

// on page load: get rules that the current user is able to request with
getUserRequesterRules().then((response) => {
	OCA.Approval.userRules = response.data.ocs.data
}).catch((error) => {
	console.error(error)
})
