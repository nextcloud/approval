<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Activity;

use Exception;
use OCA\Approval\AppInfo\Application;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;

use Psr\Log\LoggerInterface;

class ActivityManager {

	public const APPROVAL_OBJECT_NODE = 'files';

	public const SUBJECT_APPROVED = 'object_approved';
	public const SUBJECT_REJECTED = 'object_rejected';
	public const SUBJECT_REQUESTED = 'approval_requested';
	public const SUBJECT_MANUALLY_REQUESTED = 'approval_manually_requested';
	public const SUBJECT_REQUESTED_ORIGIN = 'approval_requested_origin';
	/**
	 * @var IManager
	 */
	private $manager;
	/**
	 * @var IL10N
	 */
	private $l10n;
	/**
	 * @var IRootFolder
	 */
	private $root;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var string|null
	 */
	private $userId;

	public function __construct(IManager $manager,
		IL10N $l10n,
		IRootFolder $root,
		IUserManager $userManager,
		LoggerInterface $logger,
		?string $userId) {
		$this->manager = $manager;
		$this->l10n = $l10n;
		$this->root = $root;
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->userId = $userId;
	}

	/**
	 * @param string $subjectIdentifier
	 * @param array $subjectParams
	 * @param bool $ownActivity
	 * @return string
	 */
	public function getActivityFormat(string $subjectIdentifier, array $subjectParams = [], bool $ownActivity = false): string {
		$subject = '';
		switch ($subjectIdentifier) {
			case self::SUBJECT_APPROVED:
				$subject = $ownActivity ? $this->l10n->t('You approved {file}'): $this->l10n->t('{user} approved {file}');
				break;
			case self::SUBJECT_REJECTED:
				$subject = $ownActivity ? $this->l10n->t('You rejected {file}'): $this->l10n->t('{user} rejected {file}');
				break;
			case self::SUBJECT_REQUESTED:
				$subject = $this->l10n->t('Your approval was requested on {file}');
				break;
			case self::SUBJECT_MANUALLY_REQUESTED:
				$subject = $this->l10n->t('Your approval was requested on {file} by {who}');
				break;
			case self::SUBJECT_REQUESTED_ORIGIN:
				$subject = $this->l10n->t('You requested approval on {file}');
				break;
			default:
				break;
		}
		return $subject;
	}

	public function triggerEvent($objectType, $entity, $subject, $additionalParams = [], $author = null) {
		try {
			$event = $this->createEvent($objectType, $entity, $subject, $additionalParams, $author);
			if ($event !== null) {
				$this->sendToUsers($event, $entity, $subject, $additionalParams);
			}
		} catch (Exception $e) {
			// Ignore exception for undefined activities on update events
		}
	}

	/**
	 * @param $objectType
	 * @param $entity
	 * @param $subject
	 * @param array $additionalParams
	 * @param string|null $author
	 * @return IEvent|null
	 * @throws Exception
	 */
	private function createEvent($objectType, $entity, $subject, array $additionalParams = [], ?string $author = null): ?IEvent {
		$found = $this->root->getById($entity);
		if (count($found) === 0) {
			$this->logger->error('Could not create activity entry for ' . $entity . '. Node not found.', ['app' => Application::APP_ID]);
			return null;
		} else {
			$node = $found[0];
		}

		/**
		 * Automatically fetch related details for subject parameters
		 * depending on the subject
		 */
		$eventType = Application::APP_ID;
		$subjectParams = [];
		$objectName = null;
		switch ($subject) {
			// No need to enhance parameters since entity already contains the required data
			case self::SUBJECT_APPROVED:
			case self::SUBJECT_REJECTED:
			case self::SUBJECT_REQUESTED:
			case self::SUBJECT_MANUALLY_REQUESTED:
			case self::SUBJECT_REQUESTED_ORIGIN:
				$subjectParams = $this->findDetailsForNode($node);
				$objectName = $node->getName();
				break;
			default:
				throw new Exception('Unknown subject for activity.');
		}
		$subjectParams['author'] = $this->l10n->t('A guest user');

		$event = $this->manager->generateEvent();
		$event->setApp(Application::APP_ID)
			->setType($eventType)
			->setAuthor($author === null ? $this->userId ?? '' : $author)
			->setObject($objectType, (int)$entity, $objectName)
			->setSubject($subject, array_merge($subjectParams, $additionalParams))
			->setTimestamp(time());

		return $event;
	}

	/**
	 * Publish activity to all users that are part of the project of a given object
	 *
	 * @param IEvent $event
	 * @param $entity
	 * @param string $subject
	 * @param array $additionalParams
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	private function sendToUsers(IEvent $event, $entity, string $subject, array $additionalParams): void {
		/*
		switch ($event->getObjectType()) {
			case self::APPROVAL_OBJECT_NODE:
		*/

		$userIds = [];
		$root = $this->root;
		if ($subject === self::SUBJECT_REQUESTED || $subject === self::SUBJECT_MANUALLY_REQUESTED) {
			// publish to every approver, they won't see the activity entry if they don't have access
			// (check done in Acitivty\ApprovalProvider)
			$userIds = $additionalParams['users'];
		} elseif ($subject === self::SUBJECT_REQUESTED_ORIGIN) {
			$userIds[] = $additionalParams['origin_user_id'];
		} else {
			// publish for eveyone having access
			$this->userManager->callForSeenUsers(function (IUser $user) use ($event, $root, $entity, &$userIds) {
				$userId = $user->getUID();
				$userFolder = $root->getUserFolder($userId);
				$found = $userFolder->getById($entity);
				if (count($found) > 0) {
					$userIds[] = $userId;
				}
			});
		}

		foreach ($userIds as $userId) {
			$event->setAffectedUser($userId);
			/** @noinspection DisconnectedForeachInstructionInspection */
			$this->manager->publish($event);
		}
	}

	/**
	 * @param Node $node
	 * @return array[]
	 */
	private function findDetailsForNode(Node $node): array {
		$nodeInfo = [
			'id' => $node->getId(),
			'name' => $node->getName(),
		];
		return [
			'node' => $nodeInfo,
		];
	}
}
