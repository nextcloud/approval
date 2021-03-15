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
					<span>
						{{ option.displayName }}
					</span>
				</template>
				<template #singleLabel="{ option }">
					<ListItemIcon v-bind="option"
						:title="option.displayName + 'lalala'"
						:avatar-size="24"
						:no-margin="true" />
				</template>
				<template #noOptions>
					{{ t('welcome', 'No recommendations. Start typing.') }}
				</template>
				<template #noResult>
					{{ t('welcome', 'No result.') }}
				</template>
			</Multiselect>
			<!--div v-if="state.user_name && state.user_id"
				class="selected-user">
				<Avatar
					:size="20"
					:user="state.user_id"
					:tooltip-message="state.user_id"
					:show-user-status="false" />
				<span>
					{{ state.user_name }}
				</span>
			</div-->
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
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import MultiselectTags from '@nextcloud/vue/dist/Components/MultiselectTags'
import ListItemIcon from '@nextcloud/vue/dist/Components/ListItemIcon'

export default {
	name: 'ApprovalSetting',

	components: {
		Avatar,
		Multiselect,
		MultiselectTags,
		ListItemIcon,
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
			const result = this.suggestions.map((s) => {
				return {
					user: s.id,
					displayName: s.label,
					icon: 'icon-user',
				}
			})
			if (this.currentUser && this.query) {
				const lowerCurrent = this.currentUser.displayName.toLowerCase()
				const lowerQuery = this.query.toLowerCase()
				if (lowerCurrent.match(lowerQuery)) {
					result.push({
						user: this.currentUser.uid,
						displayName: this.currentUser.displayName,
						icon: 'icon-user',
					})
				}
			}
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
}
</style>
