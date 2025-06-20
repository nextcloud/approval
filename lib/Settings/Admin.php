<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Settings;

use OCA\Approval\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;

use OCP\AppFramework\Services\IInitialState;
use OCP\IGroupManager;
use OCP\Settings\IDelegatedSettings;

class Admin implements IDelegatedSettings {

	public function __construct(
		private string $appName,
		private IGroupManager $groupManager,
		private IInitialState $initialStateService,
		private ?string $userId,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$this->initialStateService->provideInitialState('is-admin', $this->groupManager->isAdmin($this->userId));
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return Application::ADMIN_SETTINGS_SECTION;
	}

	public function getPriority(): int {
		return 1;
	}

	public function getName(): ?string {
		return null;
	}

	public function getAuthorizedAppConfig(): array {
		return [];
	}
}
