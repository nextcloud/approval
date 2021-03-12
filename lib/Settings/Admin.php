<?php
namespace OCA\Approval\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;
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
		$userId = $this->config->getAppValue(Application::APP_ID, 'user_id', '');
		$userName = $this->config->getAppValue(Application::APP_ID, 'user_name', '');
		$tagPending = (int) $this->config->getAppValue(Application::APP_ID, 'tag_pending', '0');
		$tagApproved = (int) $this->config->getAppValue(Application::APP_ID, 'tag_approved', '0');
		$tagRejected = (int) $this->config->getAppValue(Application::APP_ID, 'tag_rejected', '0');

		$adminConfig = [
			'user_id' => $userId,
			'user_name' => $userName,
			'tag_pending' => $tagPending,
			'tag_approved' => $tagApproved,
			'tag_rejected' => $tagRejected,
		];
		$this->initialStateService->provideInitialState($this->appName, 'admin-config', $adminConfig);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'additional';
	}

	public function getPriority(): int {
		return 1;
	}
}
