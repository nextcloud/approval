<?php

namespace OCA\Approval\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\IURLGenerator;
use OCP\IInitialStateService;

use OCA\Approval\AppInfo\Application;

class Admin implements ISettings {
	private $request;
	private $config;
	private $dataDirPath;
	private $urlGenerator;
	private $l;

	public function __construct(string $appName,
								IL10N $l,
								IRequest $request,
								IConfig $config,
								IURLGenerator $urlGenerator,
								IInitialStateService $initialStateService,
								$userId) {
		$this->appName = $appName;
		$this->urlGenerator = $urlGenerator;
		$this->request = $request;
		$this->l = $l;
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'docusign_client_id', '');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'docusign_client_secret', '');
		$token = $this->config->getAppValue(Application::APP_ID, 'docusign_token', '');

		$adminConfig = [
			'docusign_client_id' => $clientID,
			'docusign_client_secret' => $clientSecret,
			'docusign_token' => $token,
		];
		$this->initialStateService->provideInitialState($this->appName, 'docusign-config', $adminConfig);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return Application::ADMIN_SETTINGS_SECTION;
	}

	public function getPriority(): int {
		return 1;
	}
}
