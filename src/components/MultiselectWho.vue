<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSelect
		class="approval-multiselect"
		:model-value="value"
		:multiple="true"
		:loading="loadingSuggestions"
		:options="formattedSuggestions"
		:placeholder="placeholder"
		:clear-search-on-select="true"
		:close-on-select="true"
		:clearable="true"
		:user-select="false"
		:filterable="false"
		:append-to-body="false"
		v-bind="$attrs"
		@search="asyncFind"
		@update:model-value="$emit('update:value', $event)">
		<template #option="option">
			<div class="select-suggestion">
				<NcAvatar
					v-if="option.type === 'user'"
					:user="option.entityId"
					:hide-status="true" />
				<NcAvatar
					v-else-if="['group', 'circle', 'email'].includes(option.type)"
					:display-name="option.displayName"
					:is-no-user="true"
					:disable-tooltip="true"
					:hide-status="true" />
				<span class="multiselect-name">
					{{ option.displayName }}
				</span>
				<span
					:class="{
						icon: true,
						[typeIconClass[option.type]]: true,
						'multiselect-icon': true,
					}" />
			</div>
		</template>
		<template #selected-option="option">
			<NcAvatar
				v-if="option.type === 'user'"
				:user="option.entityId"
				:hide-status="true" />
			<NcAvatar
				v-else-if="['group', 'circle', 'email'].includes(option.type)"
				:display-name="option.displayName"
				:is-no-user="true"
				:disable-tooltip="true"
				:hide-status="true" />
			<span class="multiselect-name">
				{{ option.displayName }}
			</span>
			<span
				:class="{
					icon: true,
					[typeIconClass[option.type]]: true,
					'multiselect-icon': true,
				}" />
		</template>
		<template #noOptions>
			{{ t("approval", "No recommendations. Start typing.") }}
		</template>
		<template #noResult>
			{{ t("approval", "No result.") }}
		</template>
	</NcSelect>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcSelect from '@nextcloud/vue/components/NcSelect'

const typeIconClass = {
	user: 'icon-user',
	group: 'icon-group',
	circle: 'icon-circles',
}

export default {
	name: 'MultiselectWho',

	components: {
		NcAvatar,
		NcSelect,
	},

	props: {
		value: {
			type: Array,
			required: true,
		},
		types: {
			type: Array,
			// users, groups and circles
			default: () => [0, 1, 7],
		},
		placeholder: {
			type: String,
			default: t('approval', 'Who?'),
		},
		enableEmails: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['update:value'],

	data() {
		return {
			typeIconClass,
			loadingSuggestions: false,
			suggestions: [],
			query: '',
			currentUser: getCurrentUser(),
		}
	},

	computed: {
		queryIsEmail() {
			const cleanQuery = this.query.replace(/\s+/g, '')
			return /^\w+([.+-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,})+$/.test(cleanQuery)
		},
		formattedSuggestions() {
			// users suggestions (avoid selected users)
			const result = this.suggestions
				.filter((s) => {
					return (
						s.source === 'users'
            && !this.value.find((u) => u.type === 'user' && u.entityId === s.id)
					)
				})
				.map((s) => {
					return {
						entityId: s.id,
						type: 'user',
						displayName: s.label,
						id: 'user-' + s.id,
					}
				})

			// email suggestion
			const cleanQuery = this.query.replace(/\s+/g, '')
			if (
				this.enableEmails
        && /^\w+([.+-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,})+$/.test(cleanQuery)
        && !this.value.find((i) => i.type === 'email' && i.email === cleanQuery)
			) {
				result.push({
					type: 'email',
					displayName: cleanQuery,
					email: cleanQuery,
					id: 'email-' + cleanQuery,
				})
			}

			// add current user (who is absent from autocomplete suggestions)
			// if it matches the query
			if (this.currentUser && this.query) {
				const lowerCurrent = this.currentUser.displayName.toLowerCase()
				const lowerQuery = this.query.toLowerCase()
				// don't add it if it's selected
				if (
					lowerCurrent.match(lowerQuery)
          && !this.value.find(
			  (u) => u.type === 'user' && u.entityId === this.currentUser.uid,
          )
				) {
					result.push({
						entityId: this.currentUser.uid,
						type: 'user',
						displayName: this.currentUser.displayName,
						id: 'user-' + this.currentUser.uid,
					})
				}
			}

			// groups suggestions (avoid selected ones)
			const groups = this.suggestions
				.filter((s) => {
					return (
						s.source === 'groups'
            && !this.value.find((u) => u.type === 'group' && u.entityId === s.id)
					)
				})
				.map((s) => {
					return {
						entityId: s.id,
						type: 'group',
						displayName: s.label,
						id: 'group-' + s.id,
					}
				})
			result.push(...groups)

			// circles suggestions (avoid selected ones)
			const circles = this.suggestions
				.filter((s) => {
					return (
						s.source === 'circles'
            && !this.value.find((u) => u.type === 'circle' && u.entityId === s.id)
					)
				})
				.map((s) => {
					return {
						entityId: s.id,
						type: 'circle',
						displayName: s.label,
						id: 'circle-' + s.id,
					}
				})
			result.push(...circles)

			/*
			// always add selected users/groups/circles/emails at the end
			result.push(...this.value.map((w) => {
				return w.type === 'user'
					? {
						entityId: w.entityId,
						type: 'user',
						displayName: w.displayName,
						id: 'user-' + w.entityId,
					}
					: w.type === 'group'
						? {
							entityId: w.entityId,
							type: 'group',
							displayName: w.displayName,
							id: 'group-' + w.entityId,
						}
						: w.type === 'circle'
							? {
								entityId: w.entityId,
								type: 'circle',
								displayName: w.displayName,
								id: 'circle-' + w.entityId,
							}
							: {
								type: 'email',
								displayName: w.displayName,
								email: w.email,
								id: 'email-' + w.email,
							}
			}))
			*/

			return result
		},
	},

	watch: {},

	mounted() {},

	methods: {
		asyncFind(query) {
			this.query = query
			if (query === '') {
				this.suggestions = []
				return
			}
			this.loadingSuggestions = true
			const url = generateOcsUrl('core/autocomplete/get', 2).replace(/\/$/, '')
			axios
				.get(url, {
					params: {
						format: 'json',
						search: query,
						itemType: ' ',
						itemId: ' ',
						shareTypes: this.types,
					},
				})
				.then((response) => {
					this.suggestions = response.data.ocs.data
				})
				.catch((error) => {
					showError(t('approval', 'Impossible to get user/group/circle list'))
					console.error(error)
				})
				.then(() => {
					this.loadingSuggestions = false
				})
		},
	},
}
</script>

<style scoped lang="scss">
.approval-multiselect {
  .multiselect-name {
    flex-grow: 1;
    margin-left: 10px;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .multiselect-icon {
    opacity: 0.5;
    margin-left: 4px;
  }
  .select-suggestion {
    display: flex;
    align-items: center;
  }
}
</style>
