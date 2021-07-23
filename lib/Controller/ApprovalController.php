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

use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;

use OCA\Approval\AppInfo\Application;
use OCA\Approval\Service\ApprovalService;
use OCA\Approval\Service\RuleService;

class ApprovalController extends OCSController {
	private $userId;
	/**
	 * @var ApprovalService
	 */
	private $approvalService;
	/**
	 * @var RuleService
	 */
	private $ruleService;

	public function __construct(string $appName,
								IRequest $request,
								ApprovalService $approvalService,
								RuleService $ruleService,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->approvalService = $approvalService;
		$this->userId = $userId;
		$this->ruleService = $ruleService;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getUserRequesterRules(): DataResponse {
		$rules = $this->approvalService->getUserRules($this->userId, 'requesters');
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
	 * @NoAdminRequired
	 *
	 * @param int|null $since
	 * @return DataResponse
	 */
	public function getPendingNodes(?int $since = null): DataResponse {
		$nodes = $this->approvalService->getPendingNodes($this->userId, $since);
		return new DataResponse($nodes);
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
