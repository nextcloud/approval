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
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUser;
use OCP\App\IAppManager;
use OCP\Files\IRootFolder;

use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\TagAlreadyExistsException;
use OCP\Files\Node;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Constants;

use OCA\Approval\AppInfo\Application;

class UtilsService {
	private $l10n;
	private $logger;

	/**
	 * Service providing storage, circles and tags tools
	 */
	public function __construct(string $appName,
								IConfig $config,
								LoggerInterface $logger,
								IDBConnection $db,
								IUserManager $userManager,
								IAppManager $appManager,
								IShareManager $shareManager,
								IRootFolder $root,
								ISystemTagManager $tagManager,
								IL10N $l10n) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->config = $config;
		$this->db = $db;
		$this->userManager = $userManager;
		$this->appManager = $appManager;
		$this->shareManager = $shareManager;
		$this->root = $root;
		$this->tagManager = $tagManager;
	}

	/**
	 * Create one share
	 *
	 * @param Node $node
	 * @param int $type
	 * @param string $sharedWith
	 * @param string $sharedBy
	 * @param string $label
	 * @return bool success
	 */
	public function createShare(Node $node, int $type, string $sharedWith, string $sharedBy, string $label): bool {
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setPermissions(Constants::PERMISSION_READ)
			->setSharedWith($sharedWith)
			->setShareType($type)
			->setSharedBy($sharedBy)
			->setMailSend(false)
			->setExpirationDate(null);

		try {
			$share = $this->shareManager->createShare($share);
			$share->setLabel($label)
				->setNote($label)
				->setMailSend(false)
				->setStatus(IShare::STATUS_ACCEPTED);
			$share = $this->shareManager->updateShare($share);
			//// this was done instead of ->setStatus() but it does not seem to work all the time
			// if ($type === IShare::TYPE_USER) {
			// 	try {
			// 		$this->shareManager->acceptShare($share, $sharedWith);
			// 	} catch (GenericShareException $e) {
			// 		$this->logger->warning('Approval GenericShareException error : '.$e->getMessage(), ['app' => $this->appName]);
			// 	} catch (\Throwable | \Exception $e) {
			// 		$this->logger->warning('Approval sharing error : '.$e->getMessage(), ['app' => $this->appName]);
			// 	}
			// }
			return true;
		} catch (GenericShareException | \Exception $e) {
			return false;
		}
	}

	/**
	 * Check if a user is in a given circle
	 *
	 * @param string $userId
	 * @param string $circleId
	 * @return bool
	 */
	public function isUserInCircle(string $userId, string $circleId): bool {
		$circlesManager = \OC::$server->get(\OCA\Circles\CirclesManager::class);
		$circlesManager->startSuperSession();
		try {
			$circle = $circlesManager->getCircle($circleId);
		} catch (\OCA\Circles\Exceptions\CircleNotFoundException $e) {
			$circlesManager->stopSession();
			return false;
		}
		// is the circle owner
		$owner = $circle->getOwner();
		// the owner is also a member so this might be useless...
		if ($owner->getUserType() === 1 && $owner->getUserId() === $userId) {
			$circlesManager->stopSession();
			return true;
		} else {
			$members = $circle->getMembers();
			foreach ($members as $m) {
				// is member of this circle
				if ($m->getUserType() === 1 && $m->getUserId() === $userId) {
					$circlesManager->stopSession();
					return true;
				}
			}
		}
		$circlesManager->stopSession();
		return false;
	}

	/**
	 * Check if user has access to a given file
	 *
	 * @param int $fileId
	 * @param string|null $userId
	 * @return bool
	 */
	public function userHasAccessTo(int $fileId, ?string $userId): bool {
		$user = $this->userManager->get($userId);
		if ($user instanceof IUser) {
			$userFolder = $this->root->getUserFolder($userId);
			$found = $userFolder->getById($fileId);
			return count($found) > 0;
		}
		return false;
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
}
