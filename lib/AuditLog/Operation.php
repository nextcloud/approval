<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\AuditLog;

/**
 * Operations Approval writes to the admin audit log, in the form `app.entity.action`
 *
 * The values are matched by log consumers (filters, alert rules, SIEM imports),
 * so changing one is a breaking change for them. Case names may be renamed.
 */
enum Operation: string {
	case ApprovalRequested = 'approval.file.requested';
	case ApprovalApproved = 'approval.file.approved';
	case ApprovalRejected = 'approval.file.rejected';
	case RuleCreated = 'approval.rule.created';
	case RuleUpdated = 'approval.rule.updated';
	case RuleDeleted = 'approval.rule.deleted';

	/**
	 * Short description of when the operation is logged, used for the documentation
	 */
	public function description(): string {
		return match ($this) {
			self::ApprovalRequested => 'Approval was requested for a file with a given rule',
			self::ApprovalApproved => 'A pending approval for a file was granted',
			self::ApprovalRejected => 'A pending approval for a file was rejected',
			self::RuleCreated => 'An approval rule was created',
			self::RuleUpdated => 'An approval rule was changed (tags, parties or options)',
			self::RuleDeleted => 'An approval rule was deleted',
		};
	}
}
