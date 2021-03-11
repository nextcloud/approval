<template>
	<div id="approval_prefs" class="section">
		<h2>
			<a class="icon icon-approval" />
			{{ t('approval', 'Approval') }}
		</h2>
		<br><br>
		<p class="settings-hint">
			{{ t('approval', 'NYI') }}
		</p>
		<div id="approval-content">
			<div class="approval-grid-form">
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
	name: 'PersonalSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('approval', 'user-config'),
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onInput() {
			delay(() => {
				this.saveOptions({ whatever: this.state.whatever })
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/approval/config')
			axios.put(url, req).then((response) => {
				showSuccess(t('approval', 'Approval options saved'))
			}).catch((error) => {
				showError(
					t('approval', 'Failed to save Approval options')
					+ ': ' + (error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			}).then(() => {
			})
		},
	},
}
</script>

<style scoped lang="scss">
#approval_prefs .icon {
	display: inline-block;
	width: 32px;

	#approval-content {
		margin-left: 40px;

		.approval-grid-form label {
			line-height: 38px;
		}

		.approval-grid-form input {
			width: 100%;
		}

		.approval-grid-form {
			max-width: 600px;
			display: grid;
			grid-template: 1fr / 1fr 1fr;
			button .icon {
				margin-bottom: -1px;
			}
		}
	}
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
