<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Service;

use Exception;
use OCA\Approval\AppInfo\Application;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;

use OCP\IUser;
use OCP\IGroup;
use OCP\IGroupManager;
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
	 * Return false if at least one member of the group does not have access to the file
	 *
	 * @param int $fileId
	 * @param string|null $groupId
	 * @return bool
	 */
	public function groupHasAccessTo(int $fileId, ?string $groupId): bool {
		$group = $this->groupManager->get($groupId);
		if ($group instanceof IGroup) {
			foreach ($group->getUsers() as $groupUser) {
				if (!$this->userHasAccessTo($fileId, $groupUser->getUID())) {
					return false;
				}
			}
			return true;
		}
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
}
