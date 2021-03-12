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
use Psr\Log\LoggerInterface;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;

class ApprovalService {

	private $l10n;
	private $logger;

	/**
	 * Service to operate on tags
	 */
	public function __construct (string $appName,
								LoggerInterface $logger,
								ISystemTagManager $tagManager,
								ISystemTagObjectMapper $tagObjectMapper,
								IL10N $l10n) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->tagManager = $tagManager;
		$this->tagObjectMapper = $tagObjectMapper;
	}

	/**
	 * @param int $fileId
	 * @return bool
	 */
	public function isApprovalPending(int $fileId): bool {
		$tagPending = $this->tagManager->getTag('pending', true, true);
		return $this->tagObjectMapper->haveTag($fileId, 'files', $tagPending->getId());
	}

	/**
	 * @param int $fileId
	 * @return void
	 */
	public function approve(int $fileId): void {
		$tagApproved = $this->tagManager->getTag('approved', true, true);
		$this->tagObjectMapper->assignTags($fileId, 'files', $tagApproved->getId());

		$tagPending = $this->tagManager->getTag('pending', true, true);
		if ($this->tagObjectMapper->haveTag($fileId, 'files', $tagPending->getId())) {
			$this->tagObjectMapper->unassignTags($fileId, 'files', $tagPending->getId());
		}
	}

	/**
	 * @param int $fileId
	 * @return void
	 */
	public function disapprove(int $fileId): void {
		$tagRejected = $this->tagManager->getTag('rejected', true, true);
		$this->tagObjectMapper->assignTags($fileId, 'files', $tagRejected->getId());

		$tagPending = $this->tagManager->getTag('pending', true, true);
		if ($this->tagObjectMapper->haveTag($fileId, 'files', $tagPending->getId())) {
			$this->tagObjectMapper->unassignTags($fileId, 'files', $tagPending->getId());
		}
	}
}
