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
use OCP\App\IAppManager;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Approval\Service\RuleService;

class ConfigController extends Controller {
	private $userId;
	private $config;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IConfig $config,
								IUserManager $userManager,
								IAppManager $appManager,
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
		$this->appManager = $appManager;
		$this->ruleService = $ruleService;
	}

	/**
	 *
	 * @return DataResponse
	 */
	public function getRules(): DataResponse {
		$circlesEnabled = $this->appManager->isEnabledForUser('circles');

		$rules = $this->ruleService->getRules();
		foreach ($rules as $id => $rule) {
			foreach ($rule['approvers'] as $k => $elem) {
				if ($elem['type'] === 'user') {
					$user = $this->userManager->get($elem['entityId']);
					$rules[$id]['approvers'][$k]['displayName'] = $user ? $user->getDisplayName() : $elem['entityId'];
				} elseif ($elem['type'] === 'group') {
					$rules[$id]['approvers'][$k]['displayName'] = $elem['entityId'];
				} elseif ($elem['type'] === 'circle') {
					if ($circlesEnabled) {
						$circleDetails = \OCA\Circles\Api\v1\Circles::detailsCircle($elem['entityId']);
						$rules[$id]['approvers'][$k]['displayName'] = $circleDetails->getName();
					} else {
						unset($rules[$id]['approvers'][$k]);
					}
				}
			}
			foreach ($rule['requesters'] as $k => $elem) {
				if ($elem['type'] === 'user') {
					$user = $this->userManager->get($elem['entityId']);
					$rules[$id]['requesters'][$k]['displayName'] = $user ? $user->getDisplayName() : $elem['entityId'];
				} elseif ($elem['type'] === 'group') {
					$rules[$id]['requesters'][$k]['displayName'] = $elem['entityId'];
				} elseif ($elem['type'] === 'circle') {
					if ($circlesEnabled) {
						$circleDetails = \OCA\Circles\Api\v1\Circles::detailsCircle($elem['entityId']);
						$rules[$id]['requesters'][$k]['displayName'] = $circleDetails->getName();
					} else {
						unset($rules[$id]['requesters'][$k]);
					}
				}
			}
		}
		return new DataResponse($rules);
	}

	/**
	 *
	 * @return DataResponse
	 */
	public function createRule(int $tagPending, int $tagApproved, int $tagRejected,
								array $approvers, array $requesters, string $description): DataResponse {
		$result = $this->ruleService->createRule($tagPending, $tagApproved, $tagRejected, $approvers, $requesters, $description);
		return isset($result['error'])
			? new DataResponse($result, 400)
			: new DataResponse($result['id']);
	}

	/**
	 *
	 * @return DataResponse
	 */
	public function saveRule(int $id, int $tagPending, int $tagApproved, int $tagRejected,
							array $approvers, array $requesters, string $description): DataResponse {
		$result = $this->ruleService->saveRule($id, $tagPending, $tagApproved, $tagRejected, $approvers, $requesters, $description);
		return isset($result['error'])
			? new DataResponse($result, 400)
			: new DataResponse($result['id']);
	}

	/**
	 *
	 * @return DataResponse
	 */
	public function deleteRule(int $id): DataResponse {
		$result = $this->ruleService->deleteRule($id);
		return isset($result['error'])
			? new DataResponse($result, 400)
			: new DataResponse();
	}
}
