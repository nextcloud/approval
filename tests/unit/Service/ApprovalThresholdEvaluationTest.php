<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Service;

use ChristophWurst\Nextcloud\Testing\TestCase;

/**
 * Pure-unit coverage for the frozen v2 contract of
 * ApprovalService::evaluateThreshold() — exact integer basis-point
 * arithmetic (DESIGN-BRIEF.md v2, sections 3.3 and 6 item 1).
 *
 * bp semantics: 10000 ⇔ threshold 1.0, 5000 ⇔ 0.5, ...
 * approve fires when a * 10000 >= bp * total
 * reject fires when r * 10000 > (10000 - bp) * total
 */
class ApprovalThresholdEvaluationTest extends TestCase {

	public function testEvaluateThresholdRequiresUnanimousApprovalsAtBp10000(): void {
		// bp = 10000 ⇔ threshold 1.0: approve needs a * 10000 >= 10000 * n ⇔ a >= n
		$this->assertNull(ApprovalService::evaluateThreshold(0, 0, 3, 10000));
		$this->assertNull(ApprovalService::evaluateThreshold(1, 0, 3, 10000));
		$this->assertNull(ApprovalService::evaluateThreshold(2, 0, 3, 10000));
		$this->assertSame('approved', ApprovalService::evaluateThreshold(3, 0, 3, 10000));
	}

	public function testEvaluateThresholdSingleRejectFiresAtBp10000(): void {
		// reject condition at bp10000: r * 10000 > (10000 - 10000) * n ⇔ r * 10000 > 0
		// → any single rejection is a veto, even before approval is reached
		$this->assertNull(ApprovalService::evaluateThreshold(0, 0, 3, 10000));
		$this->assertSame('rejected', ApprovalService::evaluateThreshold(0, 1, 3, 10000));
		// a = 2 of 3 approvals is NOT enough at unanimity, yet the veto fires
		$this->assertSame('rejected', ApprovalService::evaluateThreshold(2, 1, 3, 10000));
	}

	public function testEvaluateThresholdMajorityAtBp5000(): void {
		// bp5000 ⇔ 0.5, n = 4: approve ⇔ a * 10000 >= 20000 ⇔ a >= 2
		//                reject ⇔ r * 10000 > 20000 ⇔ r > 2
		$this->assertNull(ApprovalService::evaluateThreshold(1, 0, 4, 5000));
		$this->assertSame('approved', ApprovalService::evaluateThreshold(2, 0, 4, 5000));
		$this->assertNull(ApprovalService::evaluateThreshold(0, 1, 4, 5000));
		$this->assertNull(ApprovalService::evaluateThreshold(0, 2, 4, 5000)); // strict '>' at the boundary
		$this->assertSame('rejected', ApprovalService::evaluateThreshold(0, 3, 4, 5000));
	}

	public function testEvaluateThresholdApproveBoundaryInclusiveRejectBoundaryExclusive(): void {
		// approve uses >=: sitting exactly on the ratio fires
		$this->assertSame('approved', ApprovalService::evaluateThreshold(2, 0, 4, 5000)); // 20000 >= 20000
		$this->assertNull(ApprovalService::evaluateThreshold(1, 0, 4, 5000)); // 10000 < 20000
		// reject uses >: sitting exactly on 1 - threshold does NOT fire
		$this->assertNull(ApprovalService::evaluateThreshold(0, 2, 4, 5000)); // 20000 == 20000
		$this->assertSame('rejected', ApprovalService::evaluateThreshold(0, 3, 4, 5000)); // 30000 > 20000
		// same boundaries at bp2500, n = 4: approve ⇔ a >= 1; reject ⇔ r * 10000 > 30000 ⇔ r > 3
		$this->assertSame('approved', ApprovalService::evaluateThreshold(1, 0, 4, 2500)); // 10000 >= 10000
		$this->assertNull(ApprovalService::evaluateThreshold(0, 0, 4, 2500));
		$this->assertNull(ApprovalService::evaluateThreshold(0, 3, 4, 2500)); // 30000 == 30000
		$this->assertSame('rejected', ApprovalService::evaluateThreshold(0, 4, 4, 2500)); // 40000 > 30000
	}

	public function testEvaluateThresholdGuardsTotalZeroAndOne(): void {
		// total = max(1, 0) → behaves like a single approver
		$this->assertSame('approved', ApprovalService::evaluateThreshold(1, 0, 0, 10000));
		$this->assertNull(ApprovalService::evaluateThreshold(0, 0, 0, 10000));
		$this->assertSame('rejected', ApprovalService::evaluateThreshold(0, 1, 0, 10000));
		// n = 1: one live approver behaves like the legacy first-decision path
		// under any valid bp (frozen invariant, section 2)
		$this->assertSame('approved', ApprovalService::evaluateThreshold(1, 0, 1, 10000));
		$this->assertSame('rejected', ApprovalService::evaluateThreshold(0, 1, 1, 10000));
		$this->assertSame('approved', ApprovalService::evaluateThreshold(1, 0, 1, 5000));
		$this->assertSame('rejected', ApprovalService::evaluateThreshold(0, 1, 1, 1));
		$this->assertNull(ApprovalService::evaluateThreshold(0, 0, 1, 5000));
	}

	public function testEvaluateThresholdCrossOutcomeImpossibleUnderFixedCount(): void {
		// For every reachable state (a + r <= n) the approve and reject conditions
		// are mutually exclusive: a * 10000 >= bp * n together with
		// r * 10000 > (10000 - bp) * n would imply a + r > n.
		// The expectation is recomputed independently (approve-first) and must
		// match the frozen function for every combination.
		foreach ([0, 1, 2, 3, 4, 5, 6] as $total) {
			$effectiveTotal = max(1, $total);
			foreach ([1, 2500, 5000, 7500, 10000] as $bp) {
				for ($approved = 0; $approved <= $total; $approved++) {
					for ($rejected = 0; $rejected <= $total - $approved; $rejected++) {
						$context = "a=$approved r=$rejected n=$total bp=$bp";
						$approveFires = $approved * 10000 >= $bp * $effectiveTotal;
						$rejectFires = $rejected * 10000 > (10000 - $bp) * $effectiveTotal;
						$this->assertFalse(
							$approveFires && $rejectFires,
							"cross-outcome must be impossible with a + r <= n: $context"
						);
						$expected = $approveFires ? 'approved' : ($rejectFires ? 'rejected' : null);
						$this->assertSame(
							$expected,
							ApprovalService::evaluateThreshold($approved, $rejected, $total, $bp),
							$context
						);
					}
				}
			}
		}
	}

	public function testEvaluateThresholdApproveBranchWinsWhenBothConditionsHold(): void {
		// Unreachable with a + r <= n (see the invariant test above); reachable
		// after a denominator shrink, where votes of departed approvers still
		// count in a/r but left n (frozen decision 2.6). The function checks
		// approve first and returns 'approved'; the service must then apply its
		// own-outcome-only finalization rule (covered in
		// ApprovalServiceRatioModeTest).
		$this->assertSame('approved', ApprovalService::evaluateThreshold(3, 1, 3, 10000));
		$this->assertSame('approved', ApprovalService::evaluateThreshold(2, 1, 1, 5000));
	}
}
