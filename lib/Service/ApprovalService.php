<?php
/**
 * Nextcloud - Approval
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2021
 */

namespace OCA\Approval\Service;

use OCP\IL10N;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use OCP\SystemTag\TagAlreadyExistsException;

use OCA\Approval\AppInfo\Application;

class ApprovalService {

	private $l10n;
	private $logger;

	/**
	 * Service to operate on tags
	 */
	public function __construct (string $appName,
								IConfig $config,
								LoggerInterface $logger,
								ISystemTagManager $tagManager,
								ISystemTagObjectMapper $tagObjectMapper,
								IL10N $l10n) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->config = $config;
		$this->tagManager = $tagManager;
		$this->tagObjectMapper = $tagObjectMapper;
	}

	/**
	 * @param string $name of the new tag
	 * @return array
	 */
	public function createTag(string $name): array {
		try {
			$this->tagManager->createTag($name, false, false);
			return [];
		} catch (TagAlreadyExistsException $e) {
			return ['error' => 'Tag already exists'];
		}
	}

	/**
	 * @param int $fileId
	 * @return bool
	 */
	public function getApprovalState(int $fileId, ?string $userId): int {
		$tagPendingId = (int) $this->config->getAppValue(Application::APP_ID, 'tag_pending', '0');
		$tagApprovedId = (int) $this->config->getAppValue(Application::APP_ID, 'tag_approved', '0');
		$tagRejectedId = (int) $this->config->getAppValue(Application::APP_ID, 'tag_rejected', '0');
		$approvalUserId = $this->config->getAppValue(Application::APP_ID, 'user_id', '');
		if ($tagPendingId !== 0 && $tagApprovedId !== 0 && $tagRejectedId !== 0 && $approvalUserId !== '') {
			try {
				// pending state is returned only for responsible user
				// other states can be returned for all users
				if ($approvalUserId === $userId && $this->tagObjectMapper->haveTag($fileId, 'files', $tagPendingId)) {
					return Application::STATE_PENDING;
				} elseif ($this->tagObjectMapper->haveTag($fileId, 'files', $tagApprovedId)) {
					return Application::STATE_APPROVED;
				} elseif ($this->tagObjectMapper->haveTag($fileId, 'files', $tagRejectedId)) {
					return Application::STATE_REJECTED;
				} else {
					return Application::STATE_NOTHING;
				}
			} catch (TagNotFoundException $e) {
				return Application::STATE_NOTHING;
			}
		} else {
			return Application::STATE_NOTHING;
		}
	}

	/**
	 * @param int $fileId
	 * @return void
	 */
	public function approve(int $fileId): void {
		$tagApprovedId = (int) $this->config->getAppValue(Application::APP_ID, 'tag_approved', '0');
		$this->tagObjectMapper->assignTags($fileId, 'files', $tagApprovedId);

		$tagPendingId = (int) $this->config->getAppValue(Application::APP_ID, 'tag_pending', '0');
		try {
			if ($this->tagObjectMapper->haveTag($fileId, 'files', $tagPendingId)) {
				$this->tagObjectMapper->unassignTags($fileId, 'files', $tagPendingId);
			}
		} catch (TagNotFoundException $e) {
		}
	}

	/**
	 * @param int $fileId
	 * @return void
	 */
	public function reject(int $fileId): void {
		$tagRejectedId = (int) $this->config->getAppValue(Application::APP_ID, 'tag_rejected', '0');
		$this->tagObjectMapper->assignTags($fileId, 'files', $tagRejectedId);

		$tagPendingId = (int) $this->config->getAppValue(Application::APP_ID, 'tag_pending', '0');
		try {
			if ($this->tagObjectMapper->haveTag($fileId, 'files', $tagPendingId)) {
				$this->tagObjectMapper->unassignTags($fileId, 'files', $tagPendingId);
			}
		} catch (TagNotFoundException $e) {
		}
	}
}
