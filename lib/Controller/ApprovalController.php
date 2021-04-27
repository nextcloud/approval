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
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Approval\Service\ApprovalService;

class ApprovalController extends Controller {
	private $userId;
	private $config;
	private $dbconnection;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IConfig $config,
								IL10N $l10n,
								LoggerInterface $logger,
								ApprovalService $approvalService,
								?string $userId) {
		parent::__construct($AppName, $request);
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->config = $config;
		$this->logger = $logger;
		$this->approvalService = $approvalService;
	}

	/**
	 * create a tag
	 * @NoCSRFRequired
	 *
	 * @param string $name of the new tag
	 * @return DataDisplayResponse
	 */
	public function createTag(string $name): DataResponse {
		$result = $this->approvalService->createTag($name);
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
	public function getUserRules(): DataResponse {
		$rules = $this->approvalService->getUserRules($this->userId);
		return new DataResponse($rules);
	}

	/**
	 * get file tags
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $fileId
	 * @return DataResponse
	 */
	public function getApprovalState(int $fileId): DataResponse {
		$state = $this->approvalService->getApprovalState($fileId, $this->userId);
		return new DataResponse($state);
	}

	/**
	 * Approve a file
	 * @NoAdminRequired
	 * @NoCSRFRequired
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
	 * @NoCSRFRequired
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
	 * @NoCSRFRequired
	 *
	 * @param int $fileId
	 * @param int $ruleId
	 * @return DataResponse
	 */
	public function request(int $fileId, int $ruleId): DataResponse {
		$result = $this->approvalService->request($fileId, $ruleId, $this->userId);
		if (isset($result['error'])) {
			return new DataResponse($result, 400);
		} else {
			return new DataResponse(1);
		}
	}
}
