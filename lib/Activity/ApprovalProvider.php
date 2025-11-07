<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Activity;

use Exception;
use InvalidArgumentException;
use OCA\Approval\AppInfo\Application;
use OCP\Activity\IEvent;
use OCP\Activity\IProvider;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;

use OCP\IUserManager;

class ApprovalProvider implements IProvider {

	/** @var string */
	private $userId;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var ActivityManager */
	private $activityManager;
	/** @var IUserManager */
	private $userManager;
	/**
	 * @var IRootFolder
	 */
	private $root;

	public function __construct(IURLGenerator $urlGenerator,
		IRootFolder $root,
		ActivityManager $activityManager,
		IUserManager $userManager,
		?string $userId) {
		$this->userId = $userId;
		$this->urlGenerator = $urlGenerator;
		$this->activityManager = $activityManager;
		$this->userManager = $userManager;
		$this->root = $root;
	}

	/**
	 * @param string $language The language which should be used for translating, e.g. "en"
	 * @param IEvent $event The current event which should be parsed
	 * @param IEvent|null $previousEvent A potential previous event which you can combine with the current one.
	 *                                   To do so, simply use setChildEvent($previousEvent) after setting the
	 *                                   combined subject on the current event.
	 * @return IEvent
	 * @throws InvalidArgumentException Should be thrown if your provider does not know this event
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== Application::APP_ID) {
			throw new InvalidArgumentException();
		}

		$event = $this->getIcon($event);

		$subjectIdentifier = $event->getSubject();
		$subjectParams = $event->getSubjectParameters();
		$ownActivity = ($event->getAuthor() === $event->getAffectedUser());

		$params = [];

		$author = $event->getAuthor();
		// get author if
		if ($author === '' && array_key_exists('author', $subjectParams)) {
			$author = $subjectParams['author'];
			$params = [
				'user' => [
					'type' => 'user',
					'id' => '0',
					'name' => $subjectParams['author']
				],
			];
			unset($subjectParams['author']);
		}
		$user = $this->userManager->get($author);
		if ($user !== null) {
			$params = [
				'user' => [
					'type' => 'user',
					'id' => $author,
					'name' => $user->getDisplayName()
				],
			];
			$event->setAuthor($author);
		}

		if ($event->getSubject() === ActivityManager::SUBJECT_APPROVED
			|| $event->getSubject() === ActivityManager::SUBJECT_REJECTED
			|| $event->getSubject() === ActivityManager::SUBJECT_REQUESTED
			|| $event->getSubject() === ActivityManager::SUBJECT_MANUALLY_REQUESTED
			|| $event->getSubject() === ActivityManager::SUBJECT_REQUESTED_ORIGIN) {
			if (isset($subjectParams['node']) && $event->getObjectName() === '') {
				$event->setObject($event->getObjectType(), $event->getObjectId(), $subjectParams['node']['name']);
			}
			// get file path for current user
			$userFolder = $this->root->getUserFolder($event->getAffectedUser());
			$found = $userFolder->getById($event->getObjectId());
			if (count($found) === 0) {
				// this avoids the event if user does not have access anymore
				return $event;
			}
			$node = $found[0];
			$path = $userFolder->getRelativePath($node->getPath());

			$file = [
				'type' => 'file',
				'id' => (string)$event->getObjectId(),
				'name' => $event->getObjectName(),
				'path' => trim($path, '/'),
				'link' => $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $event->getObjectId()])
			];
			$params['file'] = $file;
		}
		if ($event->getSubject() === ActivityManager::SUBJECT_MANUALLY_REQUESTED) {
			$params = $this->parseParamForWho($subjectParams, $params);
		}

		$event->setLink($subjectIdentifier);

		try {
			$subject = $this->activityManager->getActivityFormat($subjectIdentifier, $subjectParams, $ownActivity);
			$this->setSubjects($event, $subject, $params);
		} catch (Exception $e) {
		}
		return $event;
	}

	/**
	 * @param IEvent $event
	 * @param string $subject
	 * @param array $parameters
	 */
	protected function setSubjects(IEvent $event, string $subject, array $parameters) {
		$placeholders = $replacements = $richParameters = [];
		foreach ($parameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if (is_array($parameter) && array_key_exists('name', $parameter)) {
				$replacements[] = $parameter['name'];
				$richParameters[$placeholder] = $parameter;
			} else {
				$replacements[] = '';
			}
		}

		$event->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $richParameters);
		$event->setSubject($subject, $parameters);
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 */
	private function getIcon(IEvent $event): IEvent {
		$event->setIcon(
			$this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
			)
		);
		if ($event->getSubject() === ActivityManager::SUBJECT_APPROVED) {
			$event->setIcon(
				$this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->imagePath(Application::APP_ID, 'checkmark-green.svg')
				)
			);
		} elseif ($event->getSubject() === ActivityManager::SUBJECT_REJECTED) {
			$event->setIcon(
				$this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->imagePath(Application::APP_ID, 'close-red.svg')
				)
			);
		} elseif ($event->getSubject() === ActivityManager::SUBJECT_REQUESTED
			|| $event->getSubject() === ActivityManager::SUBJECT_MANUALLY_REQUESTED
			|| $event->getSubject() === ActivityManager::SUBJECT_REQUESTED_ORIGIN) {
			$event->setIcon(
				$this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->imagePath('core', 'actions/more.svg')
				)
			);
		}
		return $event;
	}

	/**
	 * @param array $subjectParams
	 * @param array $params
	 * @return array
	 */
	private function parseParamForWho(array $subjectParams, array $params): array {
		if (array_key_exists('who', $subjectParams)) {
			$user = $this->userManager->get($subjectParams['who']);
			$params['who'] = [
				'type' => 'user',
				'id' => $subjectParams['who'],
				'name' => $user !== null ? $user->getDisplayName() : $subjectParams['who']
			];
		}
		return $params;
	}
}
