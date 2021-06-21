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

use InvalidArgumentException;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUser;
use OCP\IConfig;
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
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var string|null
	 */
	private $userId;

	/**
	 * @param IFactory $factory
	 * @param IUserManager $userManager
	 * @param INotificationManager $notificationManager
	 * @param IURLGenerator $urlGenerator
	 * @param IConfig $config
	 * @param string|null $userId
	 */
	public function __construct(IFactory $factory,
								IUserManager $userManager,
								INotificationManager $notificationManager,
								IURLGenerator $urlGenerator,
								IConfig $config,
								?string $userId) {
		$this->factory = $factory;
		$this->userManager = $userManager;
		$this->notificationManager = $notificationManager;
		$this->url = $urlGenerator;
		$this->config = $config;
		$this->userId = $userId;
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
	 * @throws InvalidArgumentException When the notification was not prepared by a notifier
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			// Not my app => throw
			throw new InvalidArgumentException();
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

				$theme = $this->config->getUserValue($this->userId, 'accessibility', 'theme');
				$green = ($theme === 'dark')
				  ? 'E9322D'
				  : '46BA61';
				$red = ($theme === 'dark')
				  ? '46BA61'
				  : 'E9322D';
				$iconUrl = $notification->getSubject() === 'approved'
					? $this->url->getAbsoluteURL('/index.php/svg/core/actions/checkmark?color=' . $green)
					: $this->url->getAbsoluteURL('/index.php/svg/core/actions/close?color=' . $red);

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

		case 'request':
			$p = $notification->getSubjectParameters();

			$linkToFile = $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $p['fileId']]);
			$richSubjectNode = [
				'type' => 'file',
				'id' => $p['fileId'],
				'name' => $p['fileName'],
				'path' => trim($p['relativePath'], '/'),
				'link' => $linkToFile,
			];

			$subject = $l->t('Your approval was requested');
			$content = $p['type'] === 'file'
				? $l->t('Your approval was requested for file %1$s', [$p['fileName']])
				: $l->t('Your approval was requested for directory %1$s', [$p['fileName']]);
			$iconUrl = $this->url->getAbsoluteURL('/index.php/svg/core/actions/more?color=000000');

			$notification
				->setParsedSubject($subject)
				->setParsedMessage($content)
				->setLink($linkToFile)
				->setRichMessage(
					'{node}',
					[
						'node' => $richSubjectNode,
					]
				)
				->setIcon($iconUrl);
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
				$iconUrl = $this->url->getAbsoluteURL('/index.php/svg/core/actions/more?color=000000');

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
			throw new InvalidArgumentException();
		}
	}
}
