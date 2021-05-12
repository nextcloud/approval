<template>
	<div>
		<h2>
			{{ title }}
			<button v-if="selectedRule"
				class="primary"
				@click="$emit('request', selectedRule, true)">
				<span class="icon icon-checkmark" />
				{{ requestLabel }}
			</button>
		</h2>
		<ul class="rule-list">
			<li v-for="r in rules"
				:key="r.id"
				:class="{ 'rule-selected': selectedRule === r.id }">
				<div>
					<input :id="'rule-' + r.id"
						v-model="selectedRule"
						name="approval-rule"
						:value="r.id"
						type="radio">
					<label :for="'rule-' + r.id">
						{{ r.description }}
					</label>
				</div>
				<div v-if="selectedRule === r.id"
					class="approvers">
					<label>
						{{ approversLabel }}
					</label>
					<UserBubble v-for="approver in r.approvers"
						:key="approver.entityId"
						class="user-bubble"
						:user="approver.type === 'user' ? approver.entityId : undefined"
						:display-name="approver.displayName"
						:avatar-image="getAvatarImageClass(approver)"
						:size="24">
						{{ approver.displayName }}
					</UserBubble>
				</div>
			</li>
		</ul>
		<p class="settings-hint">
			<span class="icon icon-info" />
			{{ createShareHint }}
		</p>
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
			createShares: false,
			createShareHint: t('approval', 'File will be automatically shared with everybody allowed to approve.'),
			approversLabel: t('approval', 'File can be approved by'),
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
		console.debug('this.rules')
		console.debug(this.rules)
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
h2 {
	height: 40px;
	line-height: 40px;
	button {
		float: right;
	}
}

.rule-list {
	li {
		display: flex;
		flex-direction: column;
		padding: 0 0 0 10px;

		&.rule-selected {
			background-color: var(--color-background-hover);
			border-radius: var(--border-radius-large);
			padding: 10px;
		}
		>div {
			display: flex;
			align-items: center;
			flex-wrap: wrap;
			&.approvers {
				margin: 10px 0 10px 20px;
				.user-bubble {
					color: var(--color-main-text);
					height: 24px;
					margin: 0 2px 0 2px;
				}
			}
		}
	}
}

.settings-hint {
	margin-top: 15px;
	.icon {
		display: inline-block;
	}
}
</style>
