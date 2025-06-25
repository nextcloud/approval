<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="state === 'loading'">
		<template #default="{ item }">
			<NcDashboardWidgetItem
				:id="item.id"
				:target-url="item.targetUrl"
				:avatar-url="item.avatarUrl"
				:main-text="item.mainText"
				:sub-text="item.subText">
				<template #avatar="{ avatarUrl }">
					<div class="thumbnail"
						:style="{ 'background-image': 'url(' + avatarUrl + ')' }" />
				</template>
			</NcDashboardWidgetItem>
		</template>
		<template #empty-content>
			<NcEmptyContent
				v-if="emptyContentMessage"
				:description="emptyContentMessage">
				<template #icon>
					<component :is="emptyContentIcon" />
				</template>
			</NcEmptyContent>
		</template>
	</NcDashboardWidget>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'

import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcDashboardWidget from '@nextcloud/vue/components/NcDashboardWidget'
import NcDashboardWidgetItem from '@nextcloud/vue/components/NcDashboardWidgetItem'

export default {
	name: 'DashboardPending',

	components: {
		NcDashboardWidget,
		NcDashboardWidgetItem,
		NcEmptyContent,
		CheckIcon,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			pendings: [],
			showMoreUrl: generateUrl('/apps/files'),
			showMoreText: t('approval', 'More files to approve'),
			loop: null,
			state: 'loading',
			darkThemeColor: OCA.Accessibility?.theme === 'dark' ? '181818' : 'ffffff',
			windowVisibility: true,
		}
	},

	computed: {
		items() {
			return this.pendings.map((p) => {
				return {
					id: p.file_id,
					targetUrl: generateUrl('/f/' + p.file_id),
					avatarUrl: OC.MimeType.getIconUrl(p.mimetype),
					mainText: p.file_name,
					subText: this.getSubText(p),
				}
			})
		},
		lastDate() {
			const nbPendings = this.pendings.length
			return (nbPendings > 0) ? this.pendings[0].pending_since : null
		},
		lastMoment() {
			return moment.unix(this.lastDate)
		},
		emptyContentMessage() {
			return t('approval', 'No files to approve!')
		},
		emptyContentIcon() {
			return CheckIcon
		},
	},

	watch: {
		windowVisibility(newValue) {
			if (newValue) {
				this.launchLoop()
			} else {
				this.stopLoop()
			}
		},
	},

	beforeUnmount() {
		document.removeEventListener('visibilitychange', this.changeWindowVisibility)
	},

	beforeMount() {
		this.launchLoop()
		document.addEventListener('visibilitychange', this.changeWindowVisibility)
	},

	mounted() {
	},

	methods: {
		changeWindowVisibility() {
			this.windowVisibility = !document.hidden
		},
		stopLoop() {
			clearInterval(this.loop)
		},
		launchLoop() {
			this.fetchPendings()
			this.loop = setInterval(this.fetchPendings, 60000)
		},
		fetchPendings() {
			const req = {}
			if (this.lastDate) {
				req.params = {
					since: this.lastDate,
				}
			}
			const url = generateOcsUrl('apps/approval/api/v1/pendings', 2)
			axios.get(url, req).then((response) => {
				this.processPendings(response.data.ocs.data)
				this.state = 'ok'
			}).catch((error) => {
				this.stopLoop()
				showError(t('approval', 'Failed to get approval pending files'))
				if (error.response && error.response.status === 401) {
					showError(t('approval', 'Failed to get approval pending files'))
					this.state = 'error'
				} else {
					// there was an error in notif processing
					console.error(error)
				}
			})
		},
		processPendings(newPendings) {
			if (this.lastDate) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newPendings.length && this.lastDate < newPendings[i].pending_since) {
					i++
				}
				if (i > 0) {
					const toAdd = this.filter(newPendings.slice(0, i))
					this.pendings = toAdd.concat(this.pendings)
				}
			} else {
				// first time we don't check the date
				this.pendings = this.filter(newPendings)
			}
		},
		filter(pendings) {
			return pendings
		},
		getFormattedDate(p) {
			const mom = moment.unix(p.activity?.timestamp)
			return mom.format('L LT')
		},
		getSubText(p) {
			return p.activity?.userName && p.activity?.timestamp
				? t('approval', 'by {name} at {date}', { name: p.activity.userName, date: this.getFormattedDate(p) })
				: p.activity?.timestamp
					? t('approval', 'at {date}', { date: this.getFormattedDate(p) })
					: ''
		},
	},
}
</script>

<style scoped lang="scss">
.thumbnail {
	background-size: contain;
	margin-left: 8px;
	min-width: 44px;
	width: 44px;
	height: 44px;
}
</style>
