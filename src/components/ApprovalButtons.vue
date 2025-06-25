<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="buttons">
		<NcButton variant="success" @click="$emit('approve')">
			<template #icon>
				<CheckIcon :size="20" />
			</template>
			{{ approveText }}
		</NcButton>
		<NcButton variant="error" @click="$emit('reject')">
			<template #icon>
				<CloseIcon :size="20" />
			</template>
			{{ rejectText }}
		</NcButton>
	</div>
</template>

<script>
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import NcButton from '@nextcloud/vue/components/NcButton'

export default {
	name: 'ApprovalButtons',

	components: {
		NcButton,
		CheckIcon,
		CloseIcon,
	},

	props: {
		approveText: {
			type: String,
			default: t('approval', 'Approve'),
		},
		rejectText: {
			type: String,
			default: t('approval', 'Reject'),
		},
	},

	emits: ['approve', 'reject'],

	data() {
		return {}
	},

	computed: {},

	watch: {},

	mounted() {},

	methods: {},
}
</script>

<style scoped lang="scss">
.buttons {
  width: 100%;
  display: flex;
  // align-items: center;
  // justify-content: center;

  button {
    margin: 0 4px 0 4px;
    &:first-child {
      margin-left: 0;
    }
  }
}
</style>
