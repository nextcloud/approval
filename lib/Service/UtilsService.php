<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Service;

use Exception;
use OC;
use OCA\Approval\AppInfo\Application;
use OCA\Circles\CirclesManager;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;

use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ICrypto;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\TagAlreadyExistsException;
use OCP\SystemTag\TagNotFoundException;

class UtilsService {

	/**
	 * Service providing storage, circles and tags tools
	 */
	public function __construct(
		string $appName,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
		private IShareManager $shareManager,
		private IRootFolder $root,
		private ISystemTagManager $tagManager,
		private IConfig $config,
		private ICrypto $crypto,
	) {
	}

	/**
	 * Get decrypted app value
	 *
	 * @return string
	 * @throws Exception
	 */
	public function getEncryptedAppValue(string $key): string {
		$storedValue = $this->config->getAppValue(Application::APP_ID, $key);
		if ($storedValue === '') {
			return '';
		}
		return $this->crypto->decrypt($storedValue);
	}

	/**
	 * Store encrypted client secret
	 *
	 * @param string $value
	 * @return void
	 */
	public function setEncryptedAppValue(string $key, string $value): void {
		if ($value === '') {
			$this->config->setAppValue(Application::APP_ID, $key, '');
		} else {
			$encryptedClientSecret = $this->crypto->encrypt($value);
			$this->config->setAppValue(Application::APP_ID, $key, $encryptedClientSecret);
		}
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
			// share permission is not necessary for rule chaining
			// because we get the file from its owner's storage so we can share it whatsoever
			// ->setPermissions(Constants::PERMISSION_READ | Constants::PERMISSION_SHARE)
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
			$this->shareManager->updateShare($share);
			// $share = $this->shareManager->updateShare($share);
			//// this was done instead of ->setStatus() but it does not seem to work all the time
			//if ($type === IShare::TYPE_USER) {
			//	try {
			//		$this->shareManager->acceptShare($share, $sharedWith);
			//	} catch (\Throwable | \Exception $e) {
			//		$this->logger->warning('Approval sharing error : '.$e->getMessage(), ['app' => $this->appName]);
			//	}
			//}
			return true;
		} catch (Exception $e) {
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
		$circlesManager = OC::$server->get(CirclesManager::class);
		$circlesManager->startSuperSession();
		try {
			$circle = $circlesManager->getCircle($circleId);
		} catch (CircleNotFoundException $e) {
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
	 * Return false if this folder and no parents are shared with that group
	 *
	 * @param string $userId
	 * @param Node $fileNode
	 * @param string|null $groupId
	 * @return bool
	 */
	public function groupHasAccessTo(string $userId, Node $fileNode, ?string $groupId): bool {
		do {
			$groupShares = $this->shareManager->getSharesBy($userId, ISHARE::TYPE_GROUP, $fileNode);
			foreach ($groupShares as $groupShare) {
				if ($groupShare->getSharedWith() === $groupId) {
					return true;
				}
			}
			$fileNode = $fileNode->getParent();
		} while ($fileNode->getParentId() !== -1);
		return false;
	}

	/**
	 * Return true if this folder and no parents are shared with anyone
	 *
	 * @param Node $node
	 * @return bool
	 */
	public function isShared(Node $node): bool {
		$userId = $node->getOwner()->getUID();
		do {
			if (!empty($this->shareManager->getSharesBy($userId, ISHARE::TYPE_GROUP, $node))) {
				return true;
			}
			if (!empty($this->shareManager->getSharesBy($userId, ISHARE::TYPE_USER, $node))) {
				return true;
			}
			$node = $node->getParent();
		} while ($node->getParentId() !== -1);
		return false;
	}

	/**
	 * @param string $name of the new tag
	 * @return array
	 */
	public function createTag(string $name): array {
		try {
			$tag = $this->tagManager->createTag($name, true, false);
			return ['id' => $tag->getId()];
		} catch (TagAlreadyExistsException $e) {
			return ['error' => 'Tag already exists'];
		}
	}

	/**
	 * @param int $id of the tag to delete
	 * @return array
	 */
	public function deleteTag(int $id): array {
		try {
			$this->tagManager->deleteTags((string)$id);
			return ['success' => true];
		} catch (TagNotFoundException $e) {
			return ['error' => 'Tag not found'];
		}
	}

	/**
	 * Find users that need a file to be shared with, so all members of the group have it
	 * Also says if group share is the correct choice.
	 *
	 * @param Node $node of the file to check
	 * @param string|null $groupId the id of the group
	 * @return array
	 */
	public function usersNeedShare(Node $node, ?string $groupId): array {
		$group = $this->groupManager->get($groupId);
		$groupUsers = $group->getUsers();
		$usersSet = [];
		$groupsSet = []; // Stores the user if a share does not exist directly otherwise it is false
		foreach ($groupUsers as $groupUser) {
			$usersSet[$groupUser->getUID()] = $groupUser;
		}
		$ownerid = $node->getOwner()->getUID();
		do {
			foreach ($this->shareManager->getSharesBy($ownerid, ISHARE::TYPE_GROUP, $node) as $share) {
				$groupsSet[$share->getSharedWith()] = true;
			}
			foreach ($this->shareManager->getSharesBy($ownerid, ISHARE::TYPE_USER, $node) as $share) {
				if (isset($usersSet[$share->getSharedWith()])) {
					$usersSet[$share->getSharedWith()] = false;
				}
			}
			$node = $node->getParent();
		} while ($node->getParentId() !== -1);

		$groupShare = true;
		$users = [];
		foreach ($usersSet as $uid => $hasShare) {
			if ($hasShare !== false) { // User has no share
				// Checks if the user is in a group that is being shared with
				$groupList = $this->groupManager->getUserGroupIds($hasShare);
				$groupFound = false;
				foreach ($groupList as $groupId) {
					if (isset($groups[$groupId])) {
						$groupFound = true;
						break;
					}
				}
				if (!$groupFound) { // User is not in a group that is being shared with
					$users[] = $uid;
					continue;
				}
			}
			$groupShare = false;
		}
		return ['groupShare' => $groupShare, 'users' => $users];
	}
}
