<template>
	<div class="approval_rule">
		<div class="fields">
			<div class="tag">
				<span class="icon" :style="'background-image: url(' + tagPendingIconUrl + ');'" />
				<span class="field-label main-label">{{ pendingLabel }}</span>
				<MultiselectTags class="tag-select"
					:value="value.tagPending"
					:label="t('approval', 'Select pending tag')"
					:multiple="false"
					@input="update('tagPending', $event)" />
			</div>
			<div class="users">
				<span class="icon icon-group" />
				<span class="field-label">{{ whoLabel }}</span>
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
						:auto-limit="false"
						:user-select="true"
						@search-change="asyncFind"
						@update:value="update('who', $event)">
						<template #option="{option}">
							<Avatar v-if="option.userId"
								class="approval-avatar-option"
								:user="option.userId"
								:show-user-status="false" />
							<Avatar v-else-if="option.groupId || option.circleId"
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
			<div class="tag">
				<span class="icon" :style="'background-image: url(' + tagApprovedIconUrl + ');'" />
				<span class="field-label">{{ approvedLabel }}</span>
				<MultiselectTags class="tag-select"
					:value="value.tagApproved"
					:label="t('approval', 'Select approved tag')"
					:multiple="false"
					@input="update('tagApproved', $event)" />
			</div>
			<div class="tag">
				<span class="icon" :style="'background-image: url(' + tagRejectedIconUrl + ');'" />
				<span class="field-label">{{ rejectedLabel }}</span>
				<MultiselectTags class="tag-select"
					:value="value.tagRejected"
					:label="t('approval', 'Select rejected tag')"
					:multiple="false"
					@input="update('tagRejected', $event)" />
			</div>
		</div>
		<button
			v-tooltip.top="{ content: deleteRuleTooltip }"
			class="delete-rule"
			@click="$emit('delete')">
			<span :class="'icon ' + deleteIcon" />
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
		deleteIcon: {
			type: String,
			default: 'icon-delete',
		},
	},

	data() {
		return {
			tagPendingIconUrl: generateUrl('/svg/core/actions/tag?color=eca700'),
			tagApprovedIconUrl: generateUrl('/svg/core/actions/tag?color=46ba61'),
			tagRejectedIconUrl: generateUrl('/svg/core/actions/tag?color=e9322d'),
			whoLabel: t('approval', 'Who can approve'),
			pendingLabel: t('approval', 'Pending tag'),
			approvedLabel: t('approval', 'Approved tag'),
			rejectedLabel: t('approval', 'Rejected tag'),
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

			// circles (avoid selected ones)
			const circles = this.suggestions.filter((s) => {
				return s.source === 'circles' && !this.value.who.find(u => u.circleId === s.id)
			}).map((s) => {
				return {
					circleId: s.id,
					displayName: s.label,
					icon: 'icon-circle',
					trackKey: 'circle-' + s.id,
				}
			})
			result.push(...circles)

			// always add selected users/groups at the end
			result.push(...this.value.who.map((w) => {
				return w.userId
					? {
						userId: w.userId,
						displayName: w.displayName,
						icon: 'icon-user',
						trackKey: 'user-' + w.userId,
					}
					: w.groupId
						? {
							groupId: w.groupId,
							displayName: w.displayName,
							icon: 'icon-group',
							trackKey: 'group-' + w.groupId,
						}
						: {
							circleId: w.circleId,
							displayName: w.displayName,
							icon: 'icon-circle',
							trackKey: 'circle-' + w.circleId,
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
			const url = generateOcsUrl('core/autocomplete/get', 2).replace(/\/$/, '')
			axios.get(url, {
				params: {
					format: 'json',
					search: query,
					itemType: ' ',
					itemId: ' ',
					// users and groups
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
.approval_rule {
	display: flex;
	align-items: center;
	// width: 650px;
	border-radius: var(--border-radius-large);
	background: rgba(70, 186, 97, 0.1);
	padding: 5px 0 5px 0;

	.fields {
		display: flex;
		flex-direction: column;

		.tag,
		.users {
			display: flex;
			align-items: center;
			margin: 5px 0 5px 0;
		}

		.field-label {
			margin-right: 5px;
			width: 200px;
		}

		.main-label {
			font-weight: bold;
		}

		.tag-select {
			width: 250px;
		}
	}

	.delete-rule {
		margin: 0 10px 0 10px;
		width: 36px;
		height: 36px;
		padding: 0;
	}

	.icon {
		width: 16px;
		height: 16px;
		margin: 0 15px -3px 15px;
	}
	button .icon {
		margin: 0;
	}
	.approval-user-input {
		width: 250px;
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
}
</style>
