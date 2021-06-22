<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier (eneiluj) <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Approval\Dav;

use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

use OCA\Approval\AppInfo\Application;
use OCA\Approval\Service\ApprovalService;

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
			'name'        => $this->getPluginName(),
			'description' => 'Provides approval state in PROPFIND WebDav requests',
		];
	}
}
