<?php

namespace OCA\Approval\Settings;

use OCA\Approval\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;

use OCP\Settings\ISettings;

class Admin implements ISettings {

	public function __construct(
		private string $appName,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return Application::ADMIN_SETTINGS_SECTION;
	}

	public function getPriority(): int {
		return 1;
	}
}
