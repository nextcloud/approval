<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Dav;

use OCA\Approval\AppInfo\Application;
use OCA\Approval\Service\ApprovalService;
use OCA\DAV\Connector\Sabre\Directory as SabreDirectory;
use OCA\DAV\Connector\Sabre\Node as SabreNode;
use Sabre\DAV\ICollection;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class ApprovalPlugin extends ServerPlugin {
	/** @var Server */
	protected $server;
	protected ApprovalService $approvalService;
	private array $cachedDirectories;

	/**
	 * Initializes the plugin and registers event handlers
	 *
	 * @param Server $server
	 * @return void
	 */
	public function initialize(Server $server) {
		$this->server = $server;
		$this->approvalService = \OC::$server->get(ApprovalService::class);
		$server->on('propFind', [$this, 'propFind']);
		$server->on('preloadCollection', $this->preloadCollection(...));
	}


	/**
	 * @param PropFind $propFind
	 * @param INode $node
	 */
	public function propFind(PropFind $propFind, INode $node) {
		if (!$node instanceof SabreNode) {
			return;
		}
		$nodeId = $node->getId();
		$propFind->handle(
			Application::DAV_PROPERTY_APPROVAL_STATE, function () use ($nodeId) {
				return $this->approvalService->propFind($nodeId);
			}
		);
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

	/**
	 * @param PropFind $propFind
	 * @param ICollection $collection
	 * @return void
	 */
	public function preloadCollection(PropFind $propFind, ICollection $collection): void {
		if (!($collection instanceof SabreNode)) {
			return;
		}
		// need prefetch ?
		if ($collection instanceof SabreDirectory
			&& !isset($this->cachedDirectories[$collection->getPath()])
			&& (!is_null($propFind->getStatus(Application::DAV_PROPERTY_APPROVAL_STATE))
			)) {
			// note: pre-fetching only supported for depth <= 1
			$folderContent = $collection->getChildren();
			$fileIds = [(int)$collection->getId()];
			foreach ($folderContent as $info) {
				$fileIds[] = (int)$info->getId();
			}
			$this->approvalService->preloadApprovalStates($fileIds);
			$this->cachedDirectories[$collection->getPath()] = true;
		}
	}
}
