<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\AppInfo;

use OCA\Approval\Dashboard\ApprovalPendingWidget;
use OCA\Approval\Dav\ApprovalPlugin;
use OCA\Approval\Listener\LoadAdditionalScriptsListener;
use OCA\Approval\Listener\LoadSidebarScripts;
use OCA\Approval\Listener\UpdateFilesListener;
use OCA\Approval\Notification\Notifier;
use OCA\Approval\Service\ApprovalService;

use OCA\DAV\Events\SabrePluginAddEvent;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files\Event\LoadSidebar;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;

use OCP\FilesMetadata\Event\MetadataBackgroundEvent;
use OCP\SystemTag\TagAssignedEvent;
use Override;

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

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();

		$eventDispatcher = $container->get(IEventDispatcher::class);

		// listen to tag assignments
		$eventDispatcher->addListener(TagAssignedEvent::class, function (TagAssignedEvent $event) use ($container) {
			if ($event->getObjectType() === 'files') {
				/** @var ApprovalService $service */
				$service = $container->get(ApprovalService::class);
				foreach ($event->getObjectIds() as $objectId) {
					$service->handleTagAssignmentEvent((int)$objectId, $event->getTags());
				}
			}
		});
	}


	#[Override]
	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);
		$context->registerEventListener(LoadSidebar::class, LoadSidebarScripts::class);
		$context->registerNotifierService(Notifier::class);
		$context->registerDashboardWidget(ApprovalPendingWidget::class);
		$context->registerEventListener(MetadataBackgroundEvent::class, UpdateFilesListener::class);
	}

	#[Override]
	public function boot(IBootContext $context): void {
		// $context->injectFn(Closure::fromCallable([$this, 'registerHooks']));

		$eventDispatcher = $context->getServerContainer()->get(IEventDispatcher::class);
		$eventDispatcher->addListener(SabrePluginAddEvent::class, function (SabrePluginAddEvent $event) use ($context): void {
			$eventServer = $event->getServer();
			// We have to register the ApprovalPlugin here and not info.xml,
			// because info.xml plugins are loaded, after the
			// beforeMethod:* hook has already been emitted.
			$plugin = $context->getAppContainer()->get(ApprovalPlugin::class);
			$eventServer->addPlugin($plugin);
		});
	}
}
