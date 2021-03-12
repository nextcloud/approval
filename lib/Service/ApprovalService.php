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

class ApprovalService {

	private $l10n;
	private $logger;

	/**
	 * Service to operate on tags
	 */
	public function __construct (string $appName,
								LoggerInterface $logger,
								IL10N $l10n) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
	}

	/**
	 * @param int $fileId
	 * @return array
	 */
	public function getTags(int $fileId): array {
		return [];
	}

	/**
	 * @param int $fileId
	 * @return void
	 */
	public function approve(int $fileId): void {
		error_log('Approve '.$fileId);
	}

	/**
	 * @param int $fileId
	 * @return void
	 */
	public function disapprove(int $fileId): void {
		error_log('Disapprove '.$fileId);
	}
}
