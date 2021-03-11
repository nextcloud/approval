<template>
	<div id="approval_prefs" class="section">
		<h2>
			<a class="icon icon-approval" />
			{{ t('approval', 'Approval') }}
		</h2>
		<p class="settings-hint">
			{{ t('approval', 'NYI') }}
		</p>
		<div class="grid-form">
			<label for="whatever">
				<a class="icon icon-link" />
				{{ t('approval', 'Whatever') }}
			</label>
			<input id="whatever"
				v-model="state.whatever"
				type="text"
				:placeholder="t('approval', '42')"
				@input="onInput">
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'AdminSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('approval', 'admin-config'),
		}
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onInput() {
			delay(() => {
				this.saveOptions()
			}, 2000)()
		},
		saveOptions() {
			const req = {
				values: {
					whatever: this.state.whatever,
				},
			}
			const url = generateUrl('/apps/approval/admin-config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('approval', 'Approval admin options saved'))
				})
				.catch((error) => {
					showError(
						t('approval', 'Failed to save Approval admin options')
						+ ': ' + (error.response?.request?.responseText ?? '')
					)
					console.debug(error)
				})
				.then(() => {
				})
		},
	},
}
</script>

<style scoped lang="scss">
.grid-form label {
	line-height: 38px;
}

.grid-form input {
	width: 100%;
}

.grid-form {
	max-width: 500px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
	margin-left: 30px;
}

#approval_prefs .icon {
	display: inline-block;
	width: 32px;
}

#approval_prefs .grid-form .icon {
	margin-bottom: -3px;
}

.icon-approval {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
}

body.theme--dark .icon-approval {
	background-image: url(./../../img/app.svg);
}

</style>
