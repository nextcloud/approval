<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Sabre\Dav {
	interface INode {
	}

	abstract class ServerPlugin {
		abstract public function initialize(Server $server);

		public function getFeatures() {
			return [];
		}

		public function getHTTPMethods($path) {
			return [];
		}

		public function getPluginName() {
			return get_class($this);
		}

		public function getSupportedReportSet($uri) {
			return [];
		}

		public function getPluginInfo() {
			return [
				'name' => $this->getPluginName(),
				'description' => null,
				'link' => null,
			];
		}
	}
}

namespace OCA\DAV\Connector\Sabre {
	abstract class Node implements \Sabre\DAV\INode {
		public function getId() {
		}
	}
}
