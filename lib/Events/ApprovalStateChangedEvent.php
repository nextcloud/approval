<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Events;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IWebhookCompatibleEvent;

/**
 * Emitted when the approval state of a file changes:
 * requested, approved or rejected.
 *
 * When the state change goes through the approval service, it is dispatched
 * right before the corresponding system tag is (un)assigned, so listeners
 * that react to tag assignment events (e.g. workflow engines) already have
 * the context available. A tag can also be (un)assigned manually, in which
 * case no such event is emitted.
 *
 * Next to the user who performed the action, it carries the ID of the
 * user who requested the approval, so listeners can act on behalf of
 * the requester and not only of the user who performed the latest action.
 */
class ApprovalStateChangedEvent extends Event implements IWebhookCompatibleEvent {

	public function __construct(
		private int $fileId,
		private int $ruleId,
		private int $newState,
		private ?string $actorUserId,
		private ?string $requesterUserId,
	) {
		parent::__construct();
	}

	public function getFileId(): int {
		return $this->fileId;
	}

	public function getRuleId(): int {
		return $this->ruleId;
	}

	/**
	 * One of Application::STATE_PENDING, Application::STATE_APPROVED or Application::STATE_REJECTED
	 */
	public function getNewState(): int {
		return $this->newState;
	}

	/**
	 * User who performed the action:
	 * the requester on request, the approver on approve/reject
	 */
	public function getActorUserId(): ?string {
		return $this->actorUserId;
	}

	/**
	 * User who requested the approval, if known
	 */
	public function getRequesterUserId(): ?string {
		return $this->requesterUserId;
	}

	public function getWebhookSerializable(): array {
		return [
			'actorUserId' => $this->actorUserId,
			'fileId' => $this->fileId,
			'newState' => $this->newState,
			'requesterUserId' => $this->requesterUserId,
			'ruleId' => $this->ruleId,
		];
	}
}
