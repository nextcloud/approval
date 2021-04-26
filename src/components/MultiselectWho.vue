<template>
	<Multiselect
		class="approval-multiselect"
		label="displayName"
		track-by="trackKey"
		:value="value"
		:multiple="true"
		:clear-on-select="false"
		:hide-selected="false"
		:internal-search="false"
		:loading="loadingSuggestions"
		:options="formattedSuggestions"
		:placeholder="placeholder"
		:preselect-first="false"
		:preserve-search="false"
		:searchable="true"
		:auto-limit="false"
		:user-select="true"
		@search-change="asyncFind"
		@update:value="$emit('update:value', $event)">
		<template #option="{option}">
			<Avatar v-if="option.type === 'user'"
				class="approval-avatar-option"
				:user="option.entityId"
				:show-user-status="false" />
			<Avatar v-else-if="['group', 'circle'].includes(option.type)"
				class="approval-avatar-option"
				:display-name="option.displayName"
				:is-no-user="true"
				:show-user-status="false" />
			<span class="multiselect-name">
				{{ option.displayName }}
			</span>
			<span v-if="option.icon"
				:class="{ icon: true, [option.icon]: true, 'multiselect-icon': true }" />
		</template>
		<template #noOptions>
			{{ t('welcome', 'No recommendations. Start typing.') }}
		</template>
		<template #noResult>
			{{ t('welcome', 'No result.') }}
		</template>
	</Multiselect>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

export default {
	name: 'MultiselectWho',

	components: {
		Avatar,
		Multiselect,
	},

	props: {
		value: {
			type: Array,
			required: true,
		},
		placeholder: {
			type: String,
			default: t('approval', 'Who?'),
		},
	},

	data() {
		return {
			loadingSuggestions: false,
			suggestions: [],
			query: '',
			currentUser: getCurrentUser(),
		}
	},

	computed: {
		formattedSuggestions() {
			// users suggestions (avoid selected users)
			const result = this.suggestions.filter((s) => {
				return s.source === 'users' && !this.value.find(u => u.type === 'user' && u.entityId === s.id)
			}).map((s) => {
				return {
					entityId: s.id,
					type: 'user',
					displayName: s.label,
					icon: 'icon-user',
					trackKey: 'user-' + s.id,
				}
			})

			// add current user (who is absent from autocomplete suggestions)
			// if it matches the query
			if (this.currentUser && this.query) {
				const lowerCurrent = this.currentUser.displayName.toLowerCase()
				const lowerQuery = this.query.toLowerCase()
				// don't add it if it's selected
				if (lowerCurrent.match(lowerQuery) && !this.value.find(u => u.type === 'user' && u.entityId === this.currentUser.uid)) {
					result.push({
						entityId: this.currentUser.uid,
						type: 'user',
						displayName: this.currentUser.displayName,
						icon: 'icon-user',
						trackKey: 'user-' + this.currentUser.uid,
					})
				}
			}

			// groups suggestions (avoid selected ones)
			const groups = this.suggestions.filter((s) => {
				return s.source === 'groups' && !this.value.find(u => u.type === 'group' && u.entityId === s.id)
			}).map((s) => {
				return {
					entityId: s.id,
					type: 'group',
					displayName: s.label,
					icon: 'icon-group',
					trackKey: 'group-' + s.id,
				}
			})
			result.push(...groups)

			// circles suggestions (avoid selected ones)
			const circles = this.suggestions.filter((s) => {
				return s.source === 'circles' && !this.value.find(u => u.type === 'circle' && u.entityId === s.id)
			}).map((s) => {
				return {
					entityId: s.id,
					type: 'circle',
					displayName: s.label,
					icon: 'icon-circle',
					trackKey: 'circle-' + s.id,
				}
			})
			result.push(...circles)

			// always add selected users/groups/circles at the end
			result.push(...this.value.map((w) => {
				return w.type === 'user'
					? {
						entityId: w.entityId,
						type: 'user',
						displayName: w.displayName,
						icon: 'icon-user',
						trackKey: 'user-' + w.entityId,
					}
					: w.type === 'group'
						? {
							entityId: w.entityId,
							type: 'group',
							displayName: w.displayName,
							icon: 'icon-group',
							trackKey: 'group-' + w.entityId,
						}
						: {
							entityId: w.entityId,
							type: 'circle',
							displayName: w.displayName,
							icon: 'icon-circle',
							trackKey: 'circle-' + w.entityId,
						}
			}))

			console.debug('suggestions')
			console.debug(result)

			return result
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		asyncFind(query) {
			this.query = query
			if (query === '') {
				this.suggestions = []
				return
			}
			this.loadingSuggestions = true
			const url = generateOcsUrl('core/autocomplete/get', 2).replace(/\/$/, '')
			axios.get(url, {
				params: {
					format: 'json',
					search: query,
					itemType: ' ',
					itemId: ' ',
					// users, groups and circles
					shareTypes: [0, 1, 7],
				},
			}).then((response) => {
				this.suggestions = response.data.ocs.data
			}).catch((error) => {
				console.error(error)
			}).then(() => {
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
	}
	.icon-circle {
		background-image: var(--icon-circles-circles-000);
		background-size: 100% 100%;
		background-repeat: no-repeat;
		background-position: center;
	}
}
</style>
