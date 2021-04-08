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

use OCP\Activity\IEvent;
use OCP\Activity\IProvider;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IL10N;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;

use OCA\Approval\AppInfo\Application;

class ApprovalProvider implements IProvider {

	/** @var string */
	private $userId;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var ActivityManager */
	private $activityManager;
	/** @var IUserManager */
	private $userManager;
	/** @var IL10N */
	private $l10n;
	/** @var IConfig */
	private $config;

	public function __construct(IURLGenerator $urlGenerator,
								IRootFolder $root,
								ActivityManager $activityManager,
								IUserManager $userManager,
								IL10N $l10n, IConfig $config, ?string $userId) {
		$this->userId = $userId;
		$this->urlGenerator = $urlGenerator;
		$this->activityManager = $activityManager;
		$this->userManager = $userManager;
		$this->l10n = $l10n;
		$this->root = $root;
		$this->config = $config;
	}

	/**
	 * @param string $language The language which should be used for translating, e.g. "en"
	 * @param IEvent $event The current event which should be parsed
	 * @param IEvent|null $previousEvent A potential previous event which you can combine with the current one.
	 *                                   To do so, simply use setChildEvent($previousEvent) after setting the
	 *                                   combined subject on the current event.
	 * @return IEvent
	 * @throws \InvalidArgumentException Should be thrown if your provider does not know this event
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, IEvent $previousEvent = null) {
		if ($event->getApp() !== Application::APP_ID) {
			throw new \InvalidArgumentException();
		}

		$event = $this->getIcon($event);

		$subjectIdentifier = $event->getSubject();
		$subjectParams = $event->getSubjectParameters();
		$ownActivity = ($event->getAuthor() === $this->userId);

		/**
		 * Map stored parameter objects to rich string types
		 */

		$author = $event->getAuthor();
		// get author if
		if ($author === '' && array_key_exists('author', $subjectParams)) {
			$author = $subjectParams['author'];
			$params = [
				'user' => [
					'type' => 'user',
					'id' => 0,
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

		if ($event->getSubject() === ActivityManager::SUBJECT_APPROVED || $event->getSubject() === ActivityManager::SUBJECT_REJECTED) {
			if (isset($subjectParams['node']) && $event->getObjectName() === '') {
				$event->setObject($event->getObjectType(), $event->getObjectId(), $subjectParams['node']['name']);
			}
			// get file path for current user
			$userFolder = $this->root->getUserFolder($this->userId);
			$found = $userFolder->getById($event->getObjectId());
			if (count($found) === 0) {
				return null;
			}
			$node = $found[0];
			$path = $userFolder->getRelativePath($node->getPath());

			// name
			$displayName = $node->getType() === FileInfo::TYPE_FILE
				? $this->l10n->t('the file %1$s', [$event->getObjectName()])
				: $this->l10n->t('the directory %1$s', [$event->getObjectName()]);

			$file = [
				'type' => 'file',
				'id' => $event->getObjectId(),
				'name' => $displayName,
				'path' => $path,
			];
			$params['file'] = $file;
		}

		$event->setLink($subjectIdentifier);

		try {
			$subject = $this->activityManager->getActivityFormat($subjectIdentifier, $subjectParams, $ownActivity);
			$this->setSubjects($event, $subject, $params);
		} catch (\Exception $e) {
		}
		return $event;
	}

	/**
	 * @param IEvent $event
	 * @param string $subject
	 * @param array $parameters
	 */
	protected function setSubjects(IEvent $event, $subject, array $parameters) {
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

	private function getIcon(IEvent $event) {
		$event->setIcon(
			$this->urlGenerator->getAbsoluteURL(
				$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
			)
		);
		if ($event->getSubject() === ActivityManager::SUBJECT_APPROVED) {
			$event->setIcon(
				$this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->imagePath(Application::APP_ID, 'approved.svg')
				)
			);
		} elseif ($event->getSubject() === ActivityManager::SUBJECT_REJECTED) {
			$event->setIcon(
				$this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->imagePath(Application::APP_ID, 'rejected.svg')
				)
			);
		}
		return $event;
	}
}
