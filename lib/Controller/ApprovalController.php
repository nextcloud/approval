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

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Approval\AppInfo\Application;
use OCA\Approval\Service\UtilsService;
use OCA\Approval\Service\ApprovalService;
use OCA\Approval\Service\RuleService;

class ApprovalController extends Controller {
	private $userId;
	/**
	 * @var UtilsService
	 */
	private $utilsService;
	/**
	 * @var ApprovalService
	 */
	private $approvalService;
	/**
	 * @var RuleService
	 */
	private $ruleService;

	public function __construct($AppName,
								IRequest $request,
								UtilsService $utilsService,
								ApprovalService $approvalService,
								RuleService $ruleService,
								?string $userId) {
		parent::__construct($AppName, $request);
		$this->utilsService = $utilsService;
		$this->approvalService = $approvalService;
		$this->userId = $userId;
		$this->ruleService = $ruleService;
	}

	/**
	 * create a tag
	 *
	 * @param string $name of the new tag
	 * @return DataResponse
	 */
	public function createTag(string $name): DataResponse {
		$result = $this->utilsService->createTag($name);
		if (isset($result['error'])) {
			return new DataResponse($result, 400);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getUserRequesterRules(): DataResponse {
		$rules = $this->approvalService->getUserRequesterRules($this->userId);
		return new DataResponse($rules);
	}

	/**
	 * get file tags
	 * @NoAdminRequired
	 *
	 * @param int $fileId
	 * @return DataResponse
	 */
	public function getApprovalState(int $fileId): DataResponse {
		$state = $this->approvalService->getApprovalState($fileId, $this->userId);
		if ($state['state'] !== Application::STATE_NOTHING) {
			// who did that?
			$stateToLookFor = $state['state'] === Application::STATE_APPROVABLE ? Application::STATE_PENDING : $state['state'];
			$activity = $this->ruleService->getLastAction($fileId, $state['rule']['id'], $stateToLookFor);
			if (!is_null($activity)) {
				$state['userId'] = $activity['userId'];
				$state['userName'] = $activity['userName'];
				$state['timestamp'] = $activity['timestamp'];
			}
		}
		return new DataResponse($state);
	}

	/**
	 * Approve a file
	 * @NoAdminRequired
	 *
	 * @param int $fileId
	 * @return DataResponse
	 */
	public function approve(int $fileId): DataResponse {
		$this->approvalService->approve($fileId, $this->userId);
		return new DataResponse(1);
	}

	/**
	 * Reject a file
	 * @NoAdminRequired
	 *
	 * @param int $fileId
	 * @return DataResponse
	 */
	public function reject(int $fileId): DataResponse {
		$this->approvalService->reject($fileId, $this->userId);
		return new DataResponse(1);
	}

	/**
	 * Request approval for a file
	 * @NoAdminRequired
	 *
	 * @param int $fileId
	 * @param int $ruleId
	 * @param bool $createShares
	 * @return DataResponse
	 */
	public function request(int $fileId, int $ruleId, bool $createShares): DataResponse {
		$result = $this->approvalService->request($fileId, $ruleId, $this->userId, $createShares);
		if (isset($result['error'])) {
			return new DataResponse($result, 400);
		} else {
			return new DataResponse($result);
		}
	}
}
