<template>
	<div>
		<h2>
			{{ title }}
		</h2>
		<ul class="rule-list">
			<li v-for="r in rules"
				:key="r.tagPending">
				<input :id="'rule-' + r.tagPending"
					v-model="selectedRule"
					name="approval-rule"
					:value="r.tagPending"
					type="radio">
				<label :for="'rule-' + r.tagPending">
					{{ r.description }}
				</label>
				<button v-if="selectedRule === r.tagPending"
					@click="$emit('request', r.id, true)">
					{{ requestLabel }}
				</button>
			</li>
		</ul>
		<p class="settings-hint">
			<span class="icon icon-info" />
			{{ createShareHint }}
		</p>
	</div>
</template>

<script>

export default {
	name: 'RequestForm',

	components: {
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
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.rule-list {
	// list-style: inside none decimal;
	li {
		display: flex;
		align-items: center;
	}
	button {
		margin-left: 10px;
	}
}

.settings-hint {
	margin-top: 15px;
	.icon {
		display: inline-block;
	}
}
</style>
