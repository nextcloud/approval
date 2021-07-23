<?php
/**
 * Nextcloud - Approval
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2021
 */

namespace OCA\Approval\AppInfo;

use OCA\Approval\Dav\ApprovalPlugin;
use OCP\Util;
use OCP\EventDispatcher\IEventDispatcher;
use OCA\Files\Event\LoadAdditionalScriptsEvent;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\Notification\IManager as INotificationManager;
use OCP\SystemTag\MapperEvent;
use OCP\SabrePluginEvent;

use OCA\Approval\Service\ApprovalService;
use OCA\Approval\Notification\Notifier;
use OCA\Approval\Dashboard\ApprovalPendingWidget;

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
	public const DOCUSIGN_USER_INFO_REQUEST_URL = 'https://account-d.docusign.com/oauth/userinfo';
	// DAV
	public const DAV_PROPERTY_APPROVAL_STATE = '{http://nextcloud.org/ns}approval-state';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();

		$eventDispatcher = $container->get(IEventDispatcher::class);
		// load files plugin script
		$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function () {
			Util::addscript(self::APP_ID, self::APP_ID . '-filesplugin');
			Util::addStyle(self::APP_ID, 'files-style');
		});
		// notifications
		$manager = $container->get(INotificationManager::class);
		$manager->registerNotifierService(Notifier::class);

		// listen to tag assignments
		$eventDispatcher->addListener(MapperEvent::EVENT_ASSIGN, function (MapperEvent $event) use ($container) {
			if ($event->getObjectType() === 'files') {
				$service = $container->get(ApprovalService::class);
				$service->handleTagAssignmentEvent($event->getObjectId(), $event->getTags());
			}
		});
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(ApprovalPendingWidget::class);
	}

	public function boot(IBootContext $context): void {
		// $context->injectFn(Closure::fromCallable([$this, 'registerHooks']));

		$eventDispatcher = $context->getServerContainer()->get(IEventDispatcher::class);
		$eventDispatcher->addListener('OCA\DAV\Connector\Sabre::addPlugin', function (SabrePluginEvent $event) use ($context) {
			$eventServer = $event->getServer();

			if ($eventServer !== null) {
				// We have to register the ApprovalPlugin here and not info.xml,
				// because info.xml plugins are loaded, after the
				// beforeMethod:* hook has already been emitted.
				$plugin = $context->getAppContainer()->get(ApprovalPlugin::class);
				$eventServer->addPlugin($plugin);
			}
		});
	}
}
