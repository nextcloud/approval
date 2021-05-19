<template>
	<div class="request-modal">
		<h2>
			{{ title }}
		</h2>
		<p class="settings-hint">
			{{ createShareHint }}
		</p>
		<ul class="rule-list">
			<li v-for="r in rules"
				:key="r.id"
				:class="{ 'rule-selected': selectedRule === r.id }">
				<label :for="'rule-' + r.id">
					<div>
						<input :id="'rule-' + r.id"
							v-model="selectedRule"
							name="approval-rule"
							:value="r.id"
							type="radio">
						<label class="rule-title">
							{{ r.description }}
						</label>
					</div>
					<div class="approvers">
						<label>
							{{ approversLabel }}
						</label>
						<UserBubble v-for="approver in r.approvers"
							:key="approver.type + '-' + approver.entityId"
							class="user-bubble"
							:user="approver.type === 'user' ? approver.entityId : undefined"
							:display-name="approver.displayName"
							:avatar-image="getAvatarImageClass(approver)"
							:size="24" />
					</div>
				</label>
			</li>
		</ul>
		<div class="footer">
			<button
				class="cancel"
				@click="$emit('cancel')">
				{{ cancelLabel }}
			</button>
			<button
				class="primary"
				:disabled="!selectedRule"
				@click="$emit('request', selectedRule, true)">
				<span class="icon icon-checkmark-white" />
				{{ requestLabel }}
			</button>
		</div>
	</div>
</template>

<script>
import UserBubble from '@nextcloud/vue/dist/Components/UserBubble'

export default {
	name: 'RequestForm',

	components: {
		UserBubble,
	},

	props: {
		rules: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			selectedRule: null,
			title: t('approval', 'Select approval rule'),
			requestLabel: t('approval', 'Request approval'),
			cancelLabel: t('approval', 'Cancel'),
			createShares: false,
			createShareHint: t('approval', 'File will be automatically shared with everybody allowed to approve.'),
			approversLabel: t('approval', 'Can be approved by'),
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
		if (this.rules && this.rules.length === 1) {
			this.selectedRule = this.rules[0].id
		}
	},

	methods: {
		getAvatarImageClass(entity) {
			if (entity.type === 'group') {
				return 'icon-group'
			} else if (entity.type === 'circle') {
				return 'icon-circle'
			}
			return undefined
		},
	},
}
</script>

<style scoped lang="scss">
.request-modal {
	h2 {
		height: 40px;
		line-height: 40px;
	}

	.rule-list {
		li {
			display: flex;
			flex-direction: column;
			padding: 0 8px 0 12px;
			margin-bottom: 4px;
			* {
				cursor: pointer;
			}

			.approvers label,
			.rule-title {
				margin-left: 8px;
			}
			.rule-title {
				font-weight: bold;
			}

			&:hover,
			&.rule-selected {
				background-color: var(--color-background-hover);
				border-radius: var(--border-radius-large);
			}
			>label div {
				display: flex;
				align-items: center;
				flex-wrap: wrap;
				&.approvers {
					margin: -4px 0 10px 20px;
					.user-bubble {
						color: var(--color-main-text);
						height: 28px;
						margin: 4px 0 0 4px;
					}
				}
			}
			input[type=radio] {
				margin-bottom: 0px;
			}
		}
	}

	.settings-hint {
		margin: 0 0 16px 0;
		color: var(--color-text-maxcontrast);
		.icon {
			display: inline-block;
		}
	}

	.footer {
		margin-top: 16px;
		.primary {
			float: right;
		}
		.icon {
			opacity: 1;
		}
	}
}
</style>
