<?php
/**
 * Nextcloud - Approval
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2021
 */

namespace OCA\Approval\AppInfo;

use OCA\Approval\Dashboard\ApprovalPendingWidget;
use OCA\Approval\Dav\ApprovalPlugin;
use OCA\Approval\Listener\LoadAdditionalScriptsListener;
use OCA\Approval\Notification\Notifier;
use OCA\Approval\Service\ApprovalService;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;

use OCP\SabrePluginEvent;
use OCP\SystemTag\MapperEvent;

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

		// listen to tag assignments
		$eventDispatcher->addListener(MapperEvent::EVENT_ASSIGN, function (MapperEvent $event) use ($container) {
			if ($event->getObjectType() === 'files') {
				/** @var ApprovalService $service */
				$service = $container->get(ApprovalService::class);
				$service->handleTagAssignmentEvent($event->getObjectId(), $event->getTags());
			}
		});
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);
		$context->registerNotifierService(Notifier::class);
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
