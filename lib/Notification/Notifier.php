<?php
/**
 * Nextcloud - Approval
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2021
 */

namespace OCA\Approval\Notification;

use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUser;
use OCP\L10N\IFactory;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

use OCA\Approval\AppInfo\Application;

class Notifier implements INotifier {

	/** @var IFactory */
	protected $factory;

	/** @var IUserManager */
	protected $userManager;

	/** @var INotificationManager */
	protected $notificationManager;

	/** @var IURLGenerator */
	protected $url;

	/**
	 * @param IFactory $factory
	 * @param IUserManager $userManager
	 * @param INotificationManager $notificationManager
	 * @param IURLGenerator $urlGenerator
	 */
	public function __construct(IFactory $factory,
								IUserManager $userManager,
								INotificationManager $notificationManager,
								IURLGenerator $urlGenerator) {
		$this->factory = $factory;
		$this->userManager = $userManager;
		$this->notificationManager = $notificationManager;
		$this->url = $urlGenerator;
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
		return $this->lFactory->get(Application::APP_ID)->t('Approval');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			// Not my app => throw
			throw new \InvalidArgumentException();
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
					? $l->t('%1$s approved %2$s.', [$user->getDisplayName(), $p['fileName']])
					: $l->t('%1$s rejected %2$s.', [$user->getDisplayName(), $p['fileName']]);
				$iconUrl = $notification->getSubject() === 'approved'
					? $this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'approved.svg'))
					: $this->url->getAbsoluteURL($this->url->imagePath(Application::APP_ID, 'rejected.svg'));

				$notification
					->setParsedSubject($subject)
					->setParsedMessage($content)
					->setLink($linkToFile)
					->setRichMessage(
						$notification->getSubject() === 'approved' ? $l->t('{node} was approved by {user}') : $l->t('{node} was rejected by {user}'),
						[
							'user' => $richSubjectUser,
							'node' => $richSubjectNode,
						]
					)
					->setIcon($iconUrl);
			}
			return $notification;

		default:
			// Unknown subject => Unknown notification => throw
			throw new \InvalidArgumentException();
		}
	}
}
