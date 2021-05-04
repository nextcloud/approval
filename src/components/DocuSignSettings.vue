<template>
	<div id="docusign_prefs" class="section">
		<h2>
			<a class="icon icon-password" />
			{{ t('approval', 'DocuSign integration') }}
		</h2>
		<p v-if="!connected"
			class="settings-hint">
			{{ t('approval', 'If you want to use DocuSign, create an application in your DocuSign \'My Apps & Keys\' developer account settings and put the client ID (integration key) and secret below.') }}
			<br><br>
			<span class="icon icon-details" />
			{{ t('approval', 'Make sure you set a "Redirect URI" to') }}
			<b> {{ redirect_uri }} </b>
		</p>
		<div v-if="!connected"
			class="grid-form">
			<label for="docusign-client-id">
				<a class="icon icon-category-auth" />
				{{ t('approval', 'Client ID (aka integration key)') }}
			</label>
			<input id="docusign-client-id"
				v-model="state.docusign_client_id"
				type="password"
				:readonly="readonly"
				:placeholder="t('approval', 'Client ID of your application')"
				@focus="readonly = false"
				@input="onFieldInput">
			<label for="docusign-client-secret">
				<a class="icon icon-category-auth" />
				{{ t('approval', 'Application secret key') }}
			</label>
			<input id="docusign-client-secret"
				v-model="state.docusign_client_secret"
				type="password"
				:readonly="readonly"
				:placeholder="t('approval', 'Secret key of your application')"
				@focus="readonly = false"
				@input="onFieldInput">
		</div>
		<button v-if="oAuthConfigured && !connected"
			id="docusign-oauth-connect"
			:disabled="loading === true"
			:class="{ loading }"
			@click="onOAuthClick">
			<span class="icon icon-external" />
			{{ t('approval', 'Connect to DocuSign') }}
		</button>
		<div v-if="connected" class="grid-form">
			<label class="docusign-connected">
				<a class="icon icon-checkmark-color" />
				{{ t('approval', 'Connected as {user}', { user: state.user_name }) }}
			</label>
			<button id="docusign-rm-cred" @click="onLogoutClick">
				<span class="icon icon-close" />
				{{ t('approval', 'Disconnect from DocuSign') }}
			</button>
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
	name: 'DocuSignSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('approval', 'docusign-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/approval/docusign/oauth-redirect'),
		}
	},

	computed: {
		oAuthConfigured() {
			return this.state.docusign_client_id && this.state.docusign_client_secret
		},
		connected() {
			return this.state.docusign_token && this.state.docusign_token !== ''
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const dsToken = urlParams.get('docusignToken')
		if (dsToken === 'success') {
			showSuccess(t('approval', 'Successfully connected to DocuSign!'))
		} else if (dsToken === 'error') {
			showError(t('approval', 'OAuth access token could not be obtained:') + ' ' + urlParams.get('message'))
		}
	},

	methods: {
		onFieldInput() {
			delay(() => {
				this.saveOptions({
					docusign_client_id: this.state.docusign_client_id,
					docusign_client_secret: this.state.docusign_client_secret,
				})
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/approval/docusign-config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('approval', 'DocuSign admin options saved'))
				})
				.catch((error) => {
					showError(
						t('approval', 'Failed to save DocuSign admin options')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
		onOAuthClick() {
			const oauthState = Math.random().toString(36).substring(3)
			const scopes = [
				'signature',
				'user_read',
				'account_read',
			]
			const requestUrl = 'https://account-d.docusign.com/oauth/auth'
				+ '?client_id=' + encodeURIComponent(this.state.docusign_client_id)
				+ '&redirect_uri=' + encodeURIComponent(this.redirect_uri)
				+ '&response_type=code'
				+ '&state=' + encodeURIComponent(oauthState)
				+ '&scope=' + scopes.join(',')

			const req = {
				values: {
					docusign_oauth_state: oauthState,
					docusign_redirect_uri: this.redirect_uri,
				},
			}
			const url = generateUrl('/apps/approval/docusign-config')
			axios.put(url, req)
				.then((response) => {
					window.location.replace(requestUrl)
				})
				.catch((error) => {
					showError(
						t('approval', 'Failed to save DocuSign OAuth state')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
		onLogoutClick() {
			this.state.docusign_token = ''
			this.saveOptions({
				docusign_token: this.state.docusign_token,
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

#docusign_prefs {
	padding-left: 0;
	.icon {
		display: inline-block;
		width: 32px;
	}

	.grid-form .icon {
		margin-bottom: -3px;
	}
}

.icon-docusign {
	background-image: url(./../../img/app-dark.svg);
	background-size: 23px 23px;
	height: 23px;
	margin-bottom: -4px;
}

body.theme--dark .icon-docusign {
	background-image: url(./../../img/app.svg);
}

</style>
