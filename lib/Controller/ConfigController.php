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

use OCP\IUserManager;

use OCP\IConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Approval\Service\RuleService;
use OCA\Approval\AppInfo\Application;

class ConfigController extends Controller {

	private $userId;
	private $config;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IConfig $config,
								IUserManager $userManager,
								IL10N $l,
								LoggerInterface $logger,
								RuleService $ruleService,
								?string $userId) {
		parent::__construct($AppName, $request);
		$this->l = $l;
		$this->userId = $userId;
		$this->config = $config;
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->ruleService = $ruleService;
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
	public function getRules(): DataResponse {
		$rules = $this->ruleService->getRules();
		foreach ($rules as $id => $rule) {
			$users = [];
			foreach ($rule['users'] as $uid) {
				$user = $this->userManager->get($uid);
				$users[] = [
					'user' => $uid,
					'displayName' => $user ? $user->getDisplayName() : $uid,
				];
			}
			$rules[$id]['users'] = $users;
		}
		return new DataResponse($rules);
	}

	/**
	 *
	 * @return DataResponse
	 */
	public function createRule(int $tagPending, int $tagApproved, int $tagRejected, array $users): DataResponse {
		$id = $this->ruleService->createRule($tagPending, $tagApproved, $tagRejected, $users);
		return new DataResponse($id);
	}

	/**
	 *
	 * @return DataResponse
	 */
	public function saveRule(int $id, int $tagPending, int $tagApproved, int $tagRejected, array $users): DataResponse {
		$this->ruleService->saveRule($id, $tagPending, $tagApproved, $tagRejected, $users);
		return new DataResponse(1);
	}

	/**
	 *
	 * @return DataResponse
	 */
	public function deleteRule(int $id): DataResponse {
		$this->ruleService->deleteRule($id);
		return new DataResponse();
	}
}
