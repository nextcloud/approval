<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Migration;

use Closure;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010013Date20230522103817 extends SimpleMigrationStep {

	public function __construct(
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
	}

	// This migration is uneccessary, but is kept for future reference
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		//		$rawClientSecret = $this->config->getAppValue(Application::APP_ID, 'docusign_client_secret');
		//		$this->utilsService->setEncryptedAppValue('docusign_client_secret', $rawClientSecret);
	}
}
