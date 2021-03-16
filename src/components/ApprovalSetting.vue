<template>
	<div class="approval_setting">
		<span class="icon icon-user" />
		<div class="approval-user">
			<Multiselect
				class="approval-user-input"
				label="displayName"
				track-by="user"
				:value="value.users"
				:multiple="true"
				:clear-on-select="false"
				:hide-selected="false"
				:internal-search="false"
				:loading="loadingUsers"
				:options="formattedSuggestions"
				:placeholder="t('welcome', 'Who can approve?')"
				:preselect-first="false"
				:preserve-search="false"
				:searchable="true"
				:user-select="true"
				@search-change="asyncFind"
				@update:value="update('users', $event)">
				<template #option="{option}">
					<Avatar
						class="approval-avatar-option"
						:user="option.user"
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
		<span class="icon" :style="'background-image: url(' + tagPendingIconUrl + ');'" />
		<MultiselectTags class="tag-select"
			:value="value.tagPending"
			:label="t('approval', 'Select pending tag')"
			:multiple="false"
			@input="update('tagPending', $event)" />
		<span class="icon" :style="'background-image: url(' + tagApprovedIconUrl + ');'" />
		<MultiselectTags class="tag-select"
			:value="value.tagApproved"
			:label="t('approval', 'Select approved tag')"
			:multiple="false"
			@input="update('tagApproved', $event)" />
		<span class="icon" :style="'background-image: url(' + tagRejectedIconUrl + ');'" />
		<MultiselectTags class="tag-select"
			:value="value.tagRejected"
			:label="t('approval', 'Select rejected tag')"
			:multiple="false"
			@input="update('tagRejected', $event)" />
		<button @click="$emit('delete')">
			<span class="icon icon-delete" />
		</button>
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
	name: 'ApprovalSetting',

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
			tagPendingIconUrl: generateUrl('/svg/core/actions/tag?color=0082c9'),
			tagApprovedIconUrl: generateUrl('/svg/core/actions/tag?color=46ba61'),
			tagRejectedIconUrl: generateUrl('/svg/core/actions/tag?color=e9322d'),
			loadingUsers: false,
			suggestions: [],
			query: '',
			currentUser: getCurrentUser(),
		}
	},

	computed: {
		formattedSuggestions() {
			const result = this.suggestions.filter((s) => {
				return !this.value.users.find(u => u.user === s.id)
			}).map((s) => {
				return {
					user: s.id,
					displayName: s.label,
					icon: 'icon-user',
				}
			})
			// add current user (who is absent from autocomplete suggestions)
			// if it matches the query
			if (this.currentUser && this.query) {
				const lowerCurrent = this.currentUser.displayName.toLowerCase()
				const lowerQuery = this.query.toLowerCase()
				// don't add it if it's selected
				if (lowerCurrent.match(lowerQuery) && !this.value.users.find(u => u.user === this.currentUser.uid)) {
					result.push({
						user: this.currentUser.uid,
						displayName: this.currentUser.displayName,
						icon: 'icon-user',
					})
				}
			}
			// always add selected users at the end
			result.push(...this.value.users.map((u) => {
				return {
					user: u.user,
					displayName: u.displayName,
					icon: 'icon-user',
				}
			}))
			return result
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		update(key, value) {
			this.$emit('input', { ...this.value, [key]: value })
		},
		asyncFind(query) {
			this.query = query
			if (query === '') {
				this.suggestions = []
				return
			}
			this.loadingUsers = true
			console.debug(query)
			const url = generateOcsUrl('core/autocomplete/get', 2).replace(/\/$/, '')
			axios.get(url, {
				params: {
					format: 'json',
					search: query,
					itemType: ' ',
					itemId: ' ',
					shareTypes: [],
				},
			}).then((response) => {
				console.debug(response)
				this.suggestions = response.data.ocs.data
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.loadingUsers = false
			})
		},
	},
}
</script>

<style scoped lang="scss">
.approval_setting {
	display: flex;
	align-items: center;

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
		}
		.multiselect-icon {
			opacity: 0.5;
		}
	}
}
</style>
