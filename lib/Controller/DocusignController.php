<?php
/**
 * Nextcloud - Approval
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2021
 */

namespace OCA\Approval\Controller;

use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\IURLGenerator;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Controller;

use OCA\Approval\AppInfo\Application;
use OCA\Approval\Service\ApprovalService;
use OCA\Approval\Service\DocusignAPIService;

class DocusignController extends Controller {
	private $userId;
	private $config;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IConfig $config,
								IL10N $l,
								IURLGenerator $urlGenerator,
								LoggerInterface $logger,
								DocusignAPIService $docusignAPIService,
								ApprovalService $approvalService,
								?string $userId) {
		parent::__construct($AppName, $request);
		$this->l = $l;
		$this->userId = $userId;
		$this->config = $config;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->docusignAPIService = $docusignAPIService;
		$this->approvalService = $approvalService;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getDocusignInfo(): DataResponse {
		$token = $this->config->getAppValue(Application::APP_ID, 'docusign_token', '');
		$isConnected = ($token !== '');
		return new DataResponse([
			'connected' => $isConnected,
		]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function sign(int $fileId, ?string $requesterUserId = null): DataResponse {
		$token = $this->config->getAppValue(Application::APP_ID, 'docusign_token', '');
		$isConnected = ($token !== '');
		if (!$isConnected) {
			return new DataResponse(['error' => 'DocuSign admin connected account is not configured'], 401);
		}
		if (!$this->approvalService->userHasAccessTo($fileId, $this->userId)) {
			return new DataResponse(['error' => 'You don\'t have access to this file'], 401);
		}
		$signResult = $this->docusignAPIService->emailSign($fileId, $this->userId, $requesterUserId);
		if (isset($signResult['error'])) {
			return new DataResponse($signResult, 401);
		} else {
			return new DataResponse($signResult);
		}
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setDocusignConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		$result = [];

		if (isset($values['docusign_token'])) {
			if ($values['docusign_token'] && $values['docusign_token'] !== '') {
				// $result = $this->storeUserInfo($values['token']);
			} else {
				$this->config->deleteAppValue(Application::APP_ID, 'docusign_user_email');
				$this->config->deleteAppValue(Application::APP_ID, 'docusign_user_name');
				$this->config->deleteAppValue(Application::APP_ID, 'docusign_user_account_id');
				$this->config->deleteAppValue(Application::APP_ID, 'docusign_token');
				$this->config->deleteAppValue(Application::APP_ID, 'docusign_refresh_token');
			}
		}
		if (isset($result['error'])) {
			return new DataResponse($result, 401);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * receive oauth code and get oauth access token
	 * @NoCSRFRequired
	 *
	 * @param string $code
	 * @param string $state
	 * @return RedirectResponse
	 */
	public function oauthRedirect(string $code = '', string $state = ''): RedirectResponse {
		$configState = $this->config->getAppValue(Application::APP_ID, 'docusign_oauth_state', '');
		$clientID = $this->config->getAppValue(Application::APP_ID, 'docusign_client_id', '');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'docusign_client_secret', '');

		// anyway, reset state
		$this->config->deleteAppValue(Application::APP_ID, 'docusign_oauth_state');

		if ($clientID && $clientSecret && $configState !== '' && $configState === $state) {
			$redirect_uri = $this->config->getAppValue(Application::APP_ID, 'docusign_redirect_uri', '');
			$docusignTokenUrl = Application::DOCUSIGN_TOKEN_REQUEST_URL;
			$result = $this->docusignAPIService->requestOAuthAccessToken($docusignTokenUrl, $clientID, $clientSecret, [
				'code' => $code,
				// 'redirect_uri' => $redirect_uri,
				'grant_type' => 'authorization_code'
			], 'POST');
			if (isset($result['access_token'])) {
				$accessToken = $result['access_token'];
				$this->config->setAppValue(Application::APP_ID, 'docusign_token', $accessToken);
				$this->config->setAppValue(Application::APP_ID, 'token_type', 'oauth');
				$refreshToken = $result['refresh_token'];
				$this->config->setAppValue(Application::APP_ID, 'docusign_refresh_token', $refreshToken);
				// get user info
				$this->storeUserInfo($accessToken);
				return new RedirectResponse(
					$this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => Application::ADMIN_SETTINGS_SECTION]) .
					'?docusignToken=success'
				);
			}
			$result = $this->l->t('Error getting OAuth access token.') . ' ' . $result['error'];
		} else {
			$result = $this->l->t('Error during OAuth exchanges');
		}
		return new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.AdminSettings.index', ['section' => Application::ADMIN_SETTINGS_SECTION]) .
			'?docusignToken=error&message=' . urlencode($result)
		);
	}

	/**
	 * @param string $accessToken
	 * @return array
	 */
	private function storeUserInfo(string $accessToken): array {
		$refreshToken = $this->config->getAppValue(Application::APP_ID, 'docusign_refresh_token', '');
		$clientID = $this->config->getAppValue(Application::APP_ID, 'docusign_client_id', '');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'docusign_client_secret', '');

		$url = 'https://account-d.docusign.com/oauth/userinfo';

		$info = $this->docusignAPIService->apiRequest($url, $accessToken, $refreshToken, $clientID, $clientSecret);
		if (isset($info['name'], $info['email'], $info['accounts']) && is_array($info['accounts']) && count($info['accounts']) > 0) {
			$this->config->setAppValue(Application::APP_ID, 'docusign_user_name', $info['name']);
			$this->config->setAppValue(Application::APP_ID, 'docusign_user_email', $info['email']);
			$accountId = '';
			$baseURI = '';
			foreach ($info['accounts'] as $account) {
				if ($account['is_default']) {
					$accountId = $account['account_id'];
					$baseURI = $account['base_uri'];
				}
			}
			$this->config->setAppValue(Application::APP_ID, 'docusign_user_account_id', $accountId);
			$this->config->setAppValue(Application::APP_ID, 'docusign_user_base_uri', $baseURI);
			// TEST
			// $this->docusignAPIService->emailSign(2232, 'julien', 'toto');
			// $this->docusignAPIService->emailSign(2232, 'julien');
			return ['docusign_user_name' => $info['name']];
		} else {
			$this->config->deleteAppValue(Application::APP_ID, 'docusign_user_name');
			$this->config->deleteAppValue(Application::APP_ID, 'docusign_user_email');
			$this->config->deleteAppValue(Application::APP_ID, 'docusign_user_account_id');
			return $info;
		}
	}
}
