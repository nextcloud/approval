<?php
/**
 * Nextcloud - Approval
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2021
 */

namespace OCA\Approval\AppInfo;

use OCP\IConfig;
use OCP\Util;
use OCA\Files\Event\LoadAdditionalScriptsEvent;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\Notification\IManager as INotificationManager;
use OCP\SystemTag\MapperEvent;

use OCA\Approval\Service\ApprovalService;
use OCA\Approval\Notification\Notifier;

/**
 * Class Application
 *
 * @package OCA\Approval\AppInfo
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'approval';
	public const ADMIN_SETTINGS_SECTION = 'approval-rules';
	// approval states
	public const STATE_NOTHING = 0;
	public const STATE_PENDING = 1;
	public const STATE_APPROVED = 2;
	public const STATE_REJECTED = 3;
	public const STATE_APPROVABLE = 4;
	// approvers/requesters types
	public const TYPE_USER = 0;
	public const TYPE_GROUP = 1;
	public const TYPE_CIRCLE = 2;
	// docusign
	public const DOCUSIGN_TOKEN_REQUEST_URL = 'https://account-d.docusign.com/oauth/token';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->container = $container;
		$this->config = $container->query(IConfig::class);

		$server = $container->getServer();
		$eventDispatcher = $server->getEventDispatcher();
		// load files plugin script
		$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function () {
			Util::addscript(self::APP_ID, self::APP_ID . '-filesplugin');
			Util::addStyle(self::APP_ID, 'files-style');
		});
		// notifications
		$manager = $container->query(INotificationManager::class);
		$manager->registerNotifierService(Notifier::class);

		// listen to tag assignments
		$eventDispatcher->addListener(MapperEvent::EVENT_ASSIGN, function (MapperEvent $event) use ($container) {
			if ($event->getObjectType() === 'files') {
				$service = $container->query(ApprovalService::class);
				$service->sendRequestNotification($event->getObjectId(), $event->getTags());
			}
		});
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
	}
}
