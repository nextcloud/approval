<?php

namespace OCA\Approval\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\IInitialStateService;

use OCA\Approval\AppInfo\Application;
use OCA\Approval\Service\DocusignAPIService;

class Admin implements ISettings {
	private $config;

	public function __construct(string $appName,
								IConfig $config,
								IInitialStateService $initialStateService,
								DocusignAPIService $docusignAPIService,
								?string $userId) {
		$this->appName = $appName;
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->docusignAPIService = $docusignAPIService;
		$this->userId = $userId;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'docusign_client_id', '');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'docusign_client_secret', '');
		$token = $this->config->getAppValue(Application::APP_ID, 'docusign_token', '');
		$refreshToken = $this->config->getAppValue(Application::APP_ID, 'docusign_refresh_token', '');

		// get and update user info
		if ($clientID && $clientSecret && $token && $refreshToken) {
			$url = Application::DOCUSIGN_USER_INFO_REQUEST_URL;
			$info = $this->docusignAPIService->apiRequest($url, $token, $refreshToken, $clientID, $clientSecret);
			$accounts = [];
			if (isset($info['name'], $info['email'], $info['accounts']) && is_array($info['accounts']) && count($info['accounts']) > 0) {
				$this->config->setAppValue(Application::APP_ID, 'docusign_user_name', $info['name']);
				$this->config->setAppValue(Application::APP_ID, 'docusign_user_email', $info['email']);
				$accounts = $info['accounts'];
				$accountId = '';
				$baseURI = '';
				foreach ($accounts as $account) {
					if ($account['is_default']) {
						$accountId = $account['account_id'];
						$baseURI = $account['base_uri'];
					}
				}
				$this->config->setAppValue(Application::APP_ID, 'docusign_user_account_id', $accountId);
				$this->config->setAppValue(Application::APP_ID, 'docusign_user_base_uri', $baseURI);
			} else {
				$this->config->deleteAppValue(Application::APP_ID, 'docusign_user_name');
				$this->config->deleteAppValue(Application::APP_ID, 'docusign_user_email');
				$this->config->deleteAppValue(Application::APP_ID, 'docusign_user_account_id');
				$this->config->deleteAppValue(Application::APP_ID, 'docusign_user_base_uri');
				return $info;
			}
		}

		$userName = $this->config->getAppValue(Application::APP_ID, 'docusign_user_name', '');
		$userEmail = $this->config->getAppValue(Application::APP_ID, 'docusign_user_email', '');

		$adminConfig = [
			'docusign_client_id' => $clientID,
			'docusign_client_secret' => $clientSecret,
			'docusign_token' => $token !== '',
			'docusign_user_name' => $userName,
			'docusign_user_email' => $userEmail,
			'docusin_user_accounts' => $accounts,
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
