<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Dav;

use OCA\Approval\AppInfo\Application;
use OCA\Approval\Service\ApprovalService;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;

use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class ApprovalPlugin extends ServerPlugin {
	/** @var Server */
	protected $server;

	/**
	 * Initializes the plugin and registers event handlers
	 *
	 * @param Server $server
	 * @return void
	 */
	public function initialize(Server $server) {
		$this->server = $server;
		$server->on('propFind', [$this, 'getApprovalState']);
	}


	/**
	 * @param PropFind $propFind
	 * @param INode $node
	 */
	public function getApprovalState(PropFind $propFind, INode $node) {
		// we instantiate the ApprovalService here to make sure sabre auth backend was triggered
		$approvalService = \OC::$server->get(ApprovalService::class);
		$approvalService->propFind($propFind, $node);
	}

	/**
	 * Returns a plugin name.
	 *
	 * Using this name other plugins will be able to access other plugins
	 * using \Sabre\DAV\Server::getPlugin
	 *
	 * @return string
	 */
	public function getPluginName(): string {
		return Application::APP_ID;
	}

	/**
	 * Returns a bunch of meta-data about the plugin.
	 *
	 * Providing this information is optional, and is mainly displayed by the
	 * Browser plugin.
	 *
	 * The description key in the returned array may contain html and will not
	 * be sanitized.
	 *
	 * @return array
	 */
	public function getPluginInfo(): array {
		return [
			'name' => $this->getPluginName(),
			'description' => 'Provides approval state in PROPFIND WebDav requests',
		];
	}
}
