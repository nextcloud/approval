<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon group-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg
			:fill="fillColor"
			:width="size"
			:height="size"
			enable-background="new 0 0 16 16"
			version="1.1"
			viewBox="0 0 16 16"
			xml:space="preserve"
			xmlns="http://www.w3.org/2000/svg">
			<path d="m10 1c-1.75 0-3 1.43-3 2.8 0 1.4 0.1 2.4 0.8 3.5 0.2 0.29 0.5 0.35 0.7 0.6 0.135 0.5 0.24 1 0.1 1.5-0.28 0.1-0.525 0.22-0.8 0.33-0.085-0.15-0.23-0.2-0.47-0.4-0.73-0.44-1.56-0.75-2.33-1.04-0.1-0.37-0.1-0.65 0-1 0.156-0.166 0.37-0.27 0.5-0.43 0.46-0.6 0.5-1.654 0.5-2.37 0-1.06-0.954-1.9-2-1.9-1.17 0-2 1-2 1.9 0 0.93 0.034 1.64 0.5 2.37 0.13 0.2 0.367 0.26 0.5 0.43 0.1 0.33 0.1 0.654 0 1-0.85 0.3-1.6 0.64-2.34 1.04-0.57 0.4-0.52 0.205-0.66 1.53-0.11 1.06 2.335 1.13 4 1.13 0.06 0 0.11 0 0.17 0-0.054 0.274-0.1 0.63-0.17 1.3-0.16 1.59 3.5 1.7 6 1.7s6.16-0.1 6-1.7c-0.215-2-0.23-1.71-1-2.3-1.1-0.654-2.45-1.17-3.6-1.6-0.15-0.56-0.04-0.97 0.1-1.5 0.235-0.25 0.5-0.36 0.7-0.6 0.7-0.885 0.8-2.425 0.8-3.5 0-1.6-1.43-2.8-3-2.8z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'GroupIcon',
	props: {
		title: {
			type: String,
			default: '',
		},
		fillColor: {
			type: String,
			default: 'currentColor',
		},
		size: {
			type: Number,
			default: 24,
		},
	},
}
</script>
