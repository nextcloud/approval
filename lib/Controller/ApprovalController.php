<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Controller;

use OCA\Approval\AppInfo\Application;
use OCA\Approval\Exceptions\OutdatedEtagException;
use OCA\Approval\Service\ApprovalService;
use OCA\Approval\Service\RuleService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\IRequest;

class ApprovalController extends OCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		private ApprovalService $approvalService,
		private RuleService $ruleService,
		private IL10N $l10n,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param int|null $fileId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function getUserRequesterRules(?int $fileId = null): DataResponse {
		$rules = $this->approvalService->getUserRules($this->userId, 'requesters', $fileId);
		return new DataResponse($rules);
	}

	/**
	 * get file tags
	 *
	 * @param int $fileId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
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
				$state['message'] = $activity['message'];
			}
		}
		return new DataResponse($state);
	}

	/**
	 * @param int|null $since
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function getPendingNodes(?int $since = null): DataResponse {
		$nodes = $this->approvalService->getPendingNodes($this->userId, $since);
		return new DataResponse($nodes);
	}

	/**
	 * Approve a file
	 *
	 * @param int $fileId
	 * @param string|null $message
	 * @param string|null $etag
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function approve(int $fileId, ?string $message = '', ?string $etag = ''): DataResponse {
		try {
			if ($this->approvalService->approve($fileId, $this->userId, $message, $etag)) {
				return new DataResponse([]);
			}
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		} catch (OutdatedEtagException) {
			return new DataResponse(['error' => $this->l10n->t('The file/folder you tried to approve has an outdated content, please reload and review it again')], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Reject a file
	 *
	 * @param int $fileId
	 * @param string|null $message
	 * @param string|null $etag
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function reject(int $fileId, ?string $message = '', ?string $etag = ''): DataResponse {
		try {
			if ($this->approvalService->reject($fileId, $this->userId, $message, $etag)) {
				return new DataResponse([]);
			}
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		} catch (OutdatedEtagException) {
			return new DataResponse(['error' => $this->l10n->t('The file/folder you tried to reject has an outdated content, please reload and review it again')], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Request approval for a file
	 *
	 * @param int $fileId
	 * @param int $ruleId
	 * @param bool $createShares
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	public function request(int $fileId, int $ruleId, bool $createShares): DataResponse {
		$result = $this->approvalService->request($fileId, $ruleId, $this->userId, $createShares);
		if (isset($result['error'])) {
			return new DataResponse($result, 400);
		} else {
			return new DataResponse($result);
		}
	}
}
