<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Listener;

use OCA\Approval\AppInfo\Application;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @implements IEventListener<Event>
 */
class LoadAdditionalScriptsListener implements IEventListener {

	public function __construct() {
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}

		Util::addInitScript(Application::APP_ID, Application::APP_ID . '-init');
		Util::addscript(Application::APP_ID, Application::APP_ID . '-filesPlugin');
		Util::addStyle(Application::APP_ID, 'files-style');
	}
}
