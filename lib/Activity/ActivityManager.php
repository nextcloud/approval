<?php
/**
 * @copyright Copyright (c) 2021 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Approval\Activity;

use InvalidArgumentException;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IUserManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\Files\IRootFolder;

use OCA\Approval\AppInfo\Application;

class ActivityManager {

	private $manager;
	private $userId;
	private $l10n;

	const APPROVAL_OBJECT_NODE = 'approval_node';

	const SUBJECT_APPROVED = 'object_approved';
	const SUBJECT_REJECTED = 'object_rejected';

	public function __construct(IManager $manager,
								IL10N $l10n,
								IRootFolder $root,
								IUserManager $userManager,
								?string $userId) {
		$this->manager = $manager;
		$this->l10n = $l10n;
		$this->root = $root;
		$this->userId = $userId;
		$this->userManager = $userManager;
	}

	/**
	 * @param $subjectIdentifier
	 * @param array $subjectParams
	 * @param bool $ownActivity
	 * @return string
	 */
	public function getActivityFormat($subjectIdentifier, $subjectParams = [], $ownActivity = false) {
		$subject = '';
		switch ($subjectIdentifier) {
			case self::SUBJECT_APPROVED:
				$subject = $ownActivity ? $this->l10n->t('You have approved {file}'): $this->l10n->t('{user} has approved {file}');
				break;
			case self::SUBJECT_REJECTED:
				$subject = $ownActivity ? $this->l10n->t('You have rejected {file}'): $this->l10n->t('{user} has rejected {file}');
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
				$this->sendToUsers($event, $entity);
			}
		} catch (\Exception $e) {
			// Ignore exception for undefined activities on update events
		}
	}

	/**
	 * @param $objectType
	 * @param $entity
	 * @param $subject
	 * @param array $additionalParams
	 * @return IEvent|null
	 * @throws \Exception
	 */
	private function createEvent($objectType, $entity, $subject, $additionalParams = [], $author = null) {
		$found = $this->root->getById($entity);
		if (count($found) === 0) {
			\OC::$server->getLogger()->error('Could not create activity entry for ' . $entity . '. Node not found.', ['app' => Application::APP_ID]);
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
		$message = null;
		$objectName = null;
		switch ($subject) {
			// No need to enhance parameters since entity already contains the required data
			case self::SUBJECT_APPROVED:
			case self::SUBJECT_REJECTED:
				$subjectParams = $this->findDetailsForNode($node);
				$objectName = $node->getName();
				// $eventType = 'approval_whatever_event';
				break;
			default:
				throw new \Exception('Unknown subject for activity.');
				break;
		}
		$subjectParams['author'] = $this->l10n->t('A guest user');

		$event = $this->manager->generateEvent();
		$event->setApp(Application::APP_ID)
			->setType($eventType)
			->setAuthor($author === null ? $this->userId ?? '' : $author)
			->setObject($objectType, (int)$entity, $objectName)
			->setSubject($subject, array_merge($subjectParams, $additionalParams))
			->setTimestamp(time());

		if ($message !== null) {
			$event->setMessage($message);
		}
		return $event;
	}

	/**
	 * Publish activity to all users that are part of the project of a given object
	 *
	 * @param IEvent $event
	 */
	private function sendToUsers(IEvent $event, $entity) {
		/*
		switch ($event->getObjectType()) {
			case self::APPROVAL_OBJECT_NODE:
		*/

		$userIds = [];
		$root = $this->root;
		// then publish for eveyone having access
		$this->userManager->callForSeenUsers(function (IUser $user) use ($event, $root, $entity, &$userIds) {
			$userId = $user->getUID();
			$userFolder = $root->getUserFolder($userId);
			$found = $userFolder->getById($entity);
			if (count($found) > 0) {
				$userIds[] = $userId;
			}
		});

		foreach ($userIds as $userId) {
			$event->setAffectedUser($userId);
			/** @noinspection DisconnectedForeachInstructionInspection */
			$this->manager->publish($event);
		}
	}

	private function findDetailsForNode($node) {
		$nodeInfo = [
			'id' => $node->getId(),
			'name' => $node->getName(),
			'path' => $node->getPath(),
		];
		return [
			'node' => $nodeInfo,
		];
	}

}
