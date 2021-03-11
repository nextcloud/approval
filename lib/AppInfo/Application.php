<?php
/**
 * Nextcloud - Approval
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2021
 */

namespace OCA\Approval\AppInfo;

use OCP\IContainer;
use OCP\IUserSession;
use OCP\Util;
use OCA\Files\Event\LoadAdditionalScriptsEvent;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

/**
 * Class Application
 *
 * @package OCA\Approval\AppInfo
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'approval';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->container = $container;
		$this->config = $container->query(\OCP\IConfig::class);

		$server = $container->getServer();
		$eventDispatcher = $server->getEventDispatcher();
		$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function() {
			Util::addscript(self::APP_ID, self::APP_ID . '-filesplugin');
		});
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
	}
}
