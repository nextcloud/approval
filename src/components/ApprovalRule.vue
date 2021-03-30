<template>
	<div class="approval_rule">
		<div v-tooltip.left="{ content: whoTooltip }"
			class="users">
			<span class="icon icon-group" />
			<div class="approval-user">
				<Multiselect
					class="approval-user-input"
					label="displayName"
					track-by="trackKey"
					:value="value.who"
					:multiple="true"
					:clear-on-select="false"
					:hide-selected="false"
					:internal-search="false"
					:loading="loadingSuggestions"
					:options="formattedSuggestions"
					:placeholder="t('welcome', 'Who can approve?')"
					:preselect-first="false"
					:preserve-search="false"
					:searchable="true"
					:user-select="true"
					@search-change="asyncFind"
					@update:value="update('who', $event)">
					<template #option="{option}">
						<Avatar v-if="option.userId"
							class="approval-avatar-option"
							:user="option.userId"
							:show-user-status="false" />
						<Avatar v-else-if="option.groupId"
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
			</div>
		</div>
		<div v-tooltip.left="{ content: pendingTooltip }"
			class="tag">
			<span class="icon" :style="'background-image: url(' + tagPendingIconUrl + ');'" />
			<MultiselectTags class="tag-select"
				:value="value.tagPending"
				:label="t('approval', 'Select pending tag')"
				:multiple="false"
				@input="update('tagPending', $event)" />
		</div>
		<div v-tooltip.left="{ content: approvedTooltip }"
			class="tag">
			<span class="icon" :style="'background-image: url(' + tagApprovedIconUrl + ');'" />
			<MultiselectTags class="tag-select"
				:value="value.tagApproved"
				:label="t('approval', 'Select approved tag')"
				:multiple="false"
				@input="update('tagApproved', $event)" />
		</div>
		<div v-tooltip.left="{ content: rejectedTooltip }"
			class="tag">
			<span class="icon" :style="'background-image: url(' + tagRejectedIconUrl + ');'" />
			<MultiselectTags class="tag-select"
				:value="value.tagRejected"
				:label="t('approval', 'Select rejected tag')"
				:multiple="false"
				@input="update('tagRejected', $event)" />
		</div>
		<button
			v-tooltip.top="{ content: deleteRuleTooltip }"
			class="delete-rule"
			@click="$emit('delete')">
			<span class="icon icon-delete" />
		</button>
		<div style="clear:both;" />
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import MultiselectTags from '@nextcloud/vue/dist/Components/MultiselectTags'

export default {
	name: 'ApprovalRule',

	components: {
		Avatar,
		Multiselect,
		MultiselectTags,
	},

	props: {
		value: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			tagPendingIconUrl: generateUrl('/svg/core/actions/tag?color=eca700'),
			tagApprovedIconUrl: generateUrl('/svg/core/actions/tag?color=46ba61'),
			tagRejectedIconUrl: generateUrl('/svg/core/actions/tag?color=e9322d'),
			whoTooltip: t('approval', 'Who can approve'),
			pendingTooltip: t('approval', 'Pending tag'),
			approvedTooltip: t('approval', 'Approved tag'),
			rejectedTooltip: t('approval', 'Rejected tag'),
			deleteRuleTooltip: t('approval', 'Delete this rule'),
			loadingSuggestions: false,
			suggestions: [],
			query: '',
			currentUser: getCurrentUser(),
		}
	},

	computed: {
		formattedSuggestions() {
			// users (avoid selected users)
			const result = this.suggestions.filter((s) => {
				return s.source === 'users' && !this.value.who.find(u => u.userId === s.id)
			}).map((s) => {
				return {
					userId: s.id,
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
				if (lowerCurrent.match(lowerQuery) && !this.value.who.find(u => u.userId === this.currentUser.uid)) {
					result.push({
						userId: this.currentUser.uid,
						displayName: this.currentUser.displayName,
						icon: 'icon-user',
						trackKey: 'user-' + this.currentUser.uid,
					})
				}
			}

			// groups (avoid selected ones)
			const groups = this.suggestions.filter((s) => {
				return s.source === 'groups' && !this.value.who.find(u => u.groupId === s.id)
			}).map((s) => {
				return {
					groupId: s.id,
					displayName: s.label,
					icon: 'icon-group',
					trackKey: 'group-' + s.id,
				}
			})
			result.push(...groups)

			// always add selected users/groups at the end
			result.push(...this.value.who.map((w) => {
				return w.userId
					? {
						userId: w.userId,
						displayName: w.displayName,
						icon: 'icon-user',
						trackKey: 'user-' + w.userId,
					}
					: {
						groupId: w.groupId,
						displayName: w.displayName,
						icon: 'icon-group',
						trackKey: 'group-' + w.groupId,
					}
			}))

			console.debug('SUSUSUSUS')
			console.debug(result)
			return result
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		update(key, value) {
			console.debug('update')
			console.debug(key)
			console.debug(value)
			if (value) {
				const backupRule = {
					...this.value,
					who: this.value.who.map(e => e),
				}
				this.$emit('input', { ...this.value, [key]: value, backupRule })
			}
		},
		asyncFind(query) {
			this.query = query
			if (query === '') {
				this.suggestions = []
				return
			}
			this.loadingSuggestions = true
			console.debug(query)
			const url = generateOcsUrl('core/autocomplete/get', 2).replace(/\/$/, '')
			axios.get(url, {
				params: {
					format: 'json',
					search: query,
					itemType: ' ',
					itemId: ' ',
					// users and groups
					shareTypes: [0, 1],
				},
			}).then((response) => {
				console.debug(response)
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
.approval_rule {
	.tag,
	.users {
		display: flex;
		align-items: center;
		float: left;
	}

	.delete-rule {
		margin: 0 0 0 15px;
		width: 36px;
		height: 36px;
		padding: 0;
	}

	.tag-select {
		width: 200px;
	}

	.icon {
		width: 16px;
		height: 16px;
		margin: 0 5px -3px 15px;
	}
	button .icon {
		margin: 0;
	}
	.approval-user-input {
		width: 250px;
		.multiselect-name {
			flex-grow: 1;
			margin-left: 10px;
		}
		.multiselect-icon {
			opacity: 0.5;
		}
	}
}
</style>
