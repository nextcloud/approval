<?php

declare(strict_types=1);

namespace OCA\Approval\Migration;

use Closure;
use OCA\Approval\AppInfo\Application;
use OCA\Approval\Service\UtilsService;
use OCP\IConfig;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version010013Date20230522103817 extends SimpleMigrationStep {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var UtilsService
	 */
	private $utilsService;

	public function __construct(
		IConfig $config,
		UtilsService $utilsService
	) {
		$this->config = $config;
		$this->utilsService = $utilsService;
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		$rawClientSecret = $this->config->getAppValue(Application::APP_ID, 'docusign_client_secret');
		$this->utilsService->setEncryptedAppValue('docusign_client_secret', $rawClientSecret);
	}
}
