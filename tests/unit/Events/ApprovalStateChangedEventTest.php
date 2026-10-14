<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Events;

use ChristophWurst\Nextcloud\Testing\TestCase;

class ApprovalStateChangedEventTest extends TestCase {
	public function testConstructorAndGetters(): void {
		$event = new ApprovalStateChangedEvent(123, 456, 2, 'robert', 'alice');

		$this->assertSame(123, $event->getFileId());
		$this->assertSame(456, $event->getRuleId());
		$this->assertSame(2, $event->getNewState());
		$this->assertSame('robert', $event->getActorUserId());
		$this->assertSame('alice', $event->getRequesterUserId());
	}

	public function testNullRequester(): void {
		$event = new ApprovalStateChangedEvent(123, 456, 1, 'alice', null);

		$this->assertSame('alice', $event->getActorUserId());
		$this->assertNull($event->getRequesterUserId());
	}

	public function testGetWebhookSerializableWithRequester(): void {
		$event = new ApprovalStateChangedEvent(123, 456, 2, 'robert', 'alice');

		$this->assertSame([
			'actorUserId' => 'robert',
			'fileId' => 123,
			'newState' => 2,
			'requesterUserId' => 'alice',
			'ruleId' => 456,
		], $event->getWebhookSerializable());
	}

	public function testGetWebhookSerializableWithoutRequester(): void {
		$event = new ApprovalStateChangedEvent(123, 456, 1, 'alice', null);

		$this->assertSame([
			'actorUserId' => 'alice',
			'fileId' => 123,
			'newState' => 1,
			'requesterUserId' => null,
			'ruleId' => 456,
		], $event->getWebhookSerializable());
	}
}
