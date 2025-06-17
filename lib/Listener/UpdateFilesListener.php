<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Approval\Listener;

use OCA\Approval\Service\ApprovalService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\FilesMetadata\Event\MetadataLiveEvent;

/** @template-implements IEventListener<MetadataLiveEvent> */
class UpdateFilesListener implements IEventListener {

	public function __construct(
		private ApprovalService $approvalService,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function handle(Event $event): void {
		if (!($event instanceof MetadataLiveEvent)) {
			return;
		}
		$fileId = $event->getNode()->getId();
		$this->approvalService->removeApprovalTags($fileId);
	}
}
