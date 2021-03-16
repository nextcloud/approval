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

use OCP\App\IAppManager;
use OCP\Files\IAppData;

use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Approval\Service\SettingService;
use OCA\Approval\AppInfo\Application;

class ConfigController extends Controller {

	private $userId;
	private $config;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IConfig $config,
								IAppManager $appManager,
								IAppData $appData,
								IL10N $l,
								LoggerInterface $logger,
								SettingService $settingService,
								?string $userId) {
		parent::__construct($AppName, $request);
		$this->l = $l;
		$this->userId = $userId;
		$this->appData = $appData;
		$this->config = $config;
		$this->logger = $logger;
		$this->settingService = $settingService;
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		return new DataResponse(1);
	}

	/**
	 *
	 * @return DataResponse
	 */
	public function getSettings(): DataResponse {
		$settings = $this->settingService->getSettings();
		foreach ($settings as $id => $setting) {
			$users = [];
			foreach ($setting['users'] as $uid) {
				$users[] = [
					'user' => $uid,
					'displayName' => $uid,
				];
			}
			$settings[$id]['users'] = $users;
		}
		return new DataResponse($settings);
	}

	/**
	 *
	 * @return DataResponse
	 */
	public function createSetting(int $tagPending, int $tagApproved, int $tagRejected, array $users): DataResponse {
		$id = $this->settingService->createSetting($tagPending, $tagApproved, $tagRejected, $users);
		return new DataResponse($id);
	}

	/**
	 *
	 * @return DataResponse
	 */
	public function saveSetting(int $id, int $tagPending, int $tagApproved, int $tagRejected, array $users): DataResponse {
		$this->settingService->saveSetting($id, $tagPending, $tagApproved, $tagRejected, $users);
		return new DataResponse(1);
	}

	/**
	 *
	 * @return DataResponse
	 */
	public function deleteSetting(int $id): DataResponse {
		$this->settingService->deleteSetting($id);
		return new DataResponse();
	}
}
