<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Notification;

use OCA\Approval\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {

	public function __construct(
		private IFactory $factory,
		private IUserManager $userManager,
		private IURLGenerator $url,
	) {
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return Application::APP_ID;
	}
	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->factory->get(Application::APP_ID)->t('Approval');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws UnknownNotificationException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			// Not my app => throw
			throw new UnknownNotificationException();
		}

		$l = $this->factory->get(Application::APP_ID, $languageCode);

		switch ($notification->getSubject()) {
			case 'approved':
			case 'rejected':
				$p = $notification->getSubjectParameters();

				$user = $this->userManager->get($p['approverId']);
				if ($user instanceof IUser) {
					$richSubjectUser = [
						'type' => 'user',
						'id' => $p['approverId'],
						'name' => $user->getDisplayName(),
					];

					$linkToFile = $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $p['fileId']]);
					$richSubjectNode = [
						'type' => 'file',
						'id' => $p['fileId'],
						'name' => $p['fileName'],
						'path' => trim($p['relativePath'], '/'),
						'link' => $linkToFile,
					];

					$subject = $p['type'] === 'file'
						? ($notification->getSubject() === 'approved'
							? $l->t('A file was approved')
							: $l->t('A file was rejected'))
						: ($notification->getSubject() === 'approved'
							? $l->t('A directory was approved')
							: $l->t('A directory was rejected'));
					$content = $notification->getSubject() === 'approved'
						? $l->t('%1$s approved %2$s', [$user->getDisplayName(), $p['fileName']])
						: $l->t('%1$s rejected %2$s', [$user->getDisplayName(), $p['fileName']]);

					$iconUrl = $notification->getSubject() === 'approved'
						? $this->url->getAbsoluteURL(
							$this->url->imagePath(Application::APP_ID, 'checkmark-green.svg')
						)
						: $this->url->getAbsoluteURL(
							$this->url->imagePath(Application::APP_ID, 'close-red.svg')
						);

					$notification
						->setParsedSubject($subject)
						->setParsedMessage($content)
						->setLink($linkToFile)
						->setRichMessage(
							$notification->getSubject() === 'approved' ? $l->t('{user} approved {node}') : $l->t('{user} rejected {node}'),
							[
								'user' => $richSubjectUser,
								'node' => $richSubjectNode,
							]
						)
						->setIcon($iconUrl);
				}
				return $notification;

			case 'manual_request':
				$p = $notification->getSubjectParameters();

				$user = $this->userManager->get($p['requesterId']);
				if ($user instanceof IUser) {
					$richSubjectUser = [
						'type' => 'user',
						'id' => $p['requesterId'],
						'name' => $user->getDisplayName(),
					];

					$linkToFile = $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $p['fileId']]);
					$richSubjectNode = [
						'type' => 'file',
						'id' => $p['fileId'],
						'name' => $p['fileName'],
						'path' => trim($p['relativePath'], '/'),
						'link' => $linkToFile,
					];

					$subject = $l->t('Your approval was requested');
					$content = $l->t('%2$s requested your approval for %1$s', [$p['fileName'], $user->getDisplayName()]);
					$iconUrl = $this->url->getAbsoluteURL(
						$this->url->imagePath('core', 'actions/more.svg')
					);

					$notification
						->setParsedSubject($subject)
						->setParsedMessage($content)
						->setLink($linkToFile)
						->setRichMessage(
							$l->t('{user} requested your approval for {node}'),
							[
								'node' => $richSubjectNode,
								'user' => $richSubjectUser,
							]
						)
						->setIcon($iconUrl);
				}
				return $notification;

			default:
				// Unknown subject => Unknown notification => throw
				throw new UnknownNotificationException();
		}
	}
}
