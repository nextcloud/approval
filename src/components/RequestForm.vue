<template>
	<div>
		<ol>
			<li v-for="r in rules"
				:key="r.tagPending">
				{{ r.description }}
				<button @click="$emit('request', r.id)">
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
// nothing
</style>
