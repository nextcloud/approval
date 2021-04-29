<template>
	<div>
		<input
			id="create-share-input"
			v-model="createShares"
			type="checkbox"
			class="checkbox">
		<label for="create-share-input">{{ createShareLabel }}</label>
		<ol class="rule-list">
			<li v-for="r in rules"
				:key="r.tagPending">
				{{ r.description }}
				<button @click="$emit('request', r.id, createShares)">
					{{ requestLabel }}
				</button>
			</li>
		</ol>
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
			requestLabel: t('approval', 'Request'),
			createShares: false,
			createShareLabel: t('approval', 'Automatically share with everybody allowed to approve'),
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
	list-style: inside none decimal;
}
</style>
