<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Service;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Approval\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\IAppManager;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use OCP\SystemTag\ISystemTagManager;

/**
 * RuleService threshold persistence and vote storage semantics against the
 * real database (DESIGN-BRIEF.md v2 §6 items 6, 3-later-row-wins and 7,
 * plus the positional 7-argument legacy call check of item 5).
 *
 * Follows the existing ApprovalServiceTest harness style: real container
 * services, per-test system tags removed in tearDown, rules tracked and
 * deleted in tearDown. The activity-table tests use file_id-only rows (no
 * foreign key on approval_activity), so no real file or user is required.
 */
class RuleServiceThresholdAndVoteTest extends TestCase {
	private const FILE_ID = 424242;

	private RuleService $ruleService;
	private UtilsService $utilsService;
	private IDBConnection $db;
	// defaults guard tearDown() when setUp() aborts mid-way (leftover tags)
	private int $idTagPending = 0;
	private int $idTagApproved = 0;
	private int $idTagRejected = 0;
	/** @var int[] */
	private array $createdRuleIds = [];

	protected function setUp(): void {
		parent::setUp();

		$app = new Application();
		$c = $app->getContainer();
		$this->db = $c->get(IDBConnection::class);
		$this->utilsService = new UtilsService(
			'approval',
			$c->get(IUserManager::class),
			$c->get(IShareManager::class),
			$c->get(IRootFolder::class),
			$c->get(ISystemTagManager::class)
		);
		$this->ruleService = new RuleService(
			'approval',
			$this->db,
			$c->get(IUserManager::class),
			$c->get(IAppManager::class),
			$c->get(ICacheFactory::class)
		);

		$r = $this->utilsService->createTag('thrvPending');
		$this->assertArrayHasKey('id', $r, 'tag thrvPending already exists (leftover run?)');
		$this->idTagPending = $r['id'];
		$r = $this->utilsService->createTag('thrvApproved');
		$this->assertArrayHasKey('id', $r, 'tag thrvApproved already exists (leftover run?)');
		$this->idTagApproved = $r['id'];
		$r = $this->utilsService->createTag('thrvRejected');
		$this->assertArrayHasKey('id', $r, 'tag thrvRejected already exists (leftover run?)');
		$this->idTagRejected = $r['id'];
	}

	protected function tearDown(): void {
		foreach ($this->createdRuleIds as $ruleId) {
			$this->ruleService->deleteRule($ruleId);
		}
		$this->utilsService->deleteTag($this->idTagPending);
		$this->utilsService->deleteTag($this->idTagApproved);
		$this->utilsService->deleteTag($this->idTagRejected);
		parent::tearDown();
	}

	public function testCreateRuleWithoutThresholdArgumentStaysLegacyNull(): void {
		// legacy positional call: the new trailing ?float parameter must stay
		// optional and default to null (§3.2)
		$r = $this->createRule(null, false);
		$this->assertArrayHasKey('id', $r);
		$ruleId = (int)$r['id'];

		$rule = $this->ruleService->getRule($ruleId);
		$this->assertNotNull($rule);
		$this->assertArrayHasKey('threshold', $rule);
		$this->assertNull($rule['threshold']);
		$this->assertArrayHasKey('thresholdBp', $rule);
		$this->assertNull($rule['thresholdBp']);
		$this->assertNull($this->fetchThresholdBp($ruleId));
	}

	public function testCreateRuleRejectsInvalidThreshold(): void {
		$before = count($this->ruleService->getRules());

		// 0 is the brief's required invalid case; 1.5 / -0.1 pin the (0, 1] range
		foreach ([0.0, 1.5, -0.1] as $threshold) {
			$r = $this->createRule($threshold);
			$this->assertArrayHasKey('error', $r, "threshold $threshold must be rejected");
			$this->assertSame('Invalid threshold', $r['error']);
		}
		// no rule row may have been created for any rejected value
		$this->assertSame($before, count($this->ruleService->getRules()));
	}

	public function testCreateRuleAcceptsThresholdOneAsTenThousandBp(): void {
		$r = $this->createRule(1.0);
		$this->assertArrayHasKey('id', $r);
		$ruleId = (int)$r['id'];

		// bp conversion: round(1.0 * 10000) = 10000 stored as INTEGER (§3.2)
		$this->assertSame(10000, $this->fetchThresholdBp($ruleId));

		$rule = $this->ruleService->getRule($ruleId);
		$this->assertSame(1.0, $rule['threshold']);
		$this->assertSame(10000, $rule['thresholdBp']);

		// getRules() exposes the same values (§3.1: both readers)
		$rules = $this->ruleService->getRules();
		$this->assertArrayHasKey($ruleId, $rules);
		$this->assertSame(1.0, $rules[$ruleId]['threshold']);
		$this->assertSame(10000, $rules[$ruleId]['thresholdBp']);
	}

	public function testCreateRuleConvertsHalfToFiveThousandBp(): void {
		$r = $this->createRule(0.5);
		$this->assertArrayHasKey('id', $r);
		$ruleId = (int)$r['id'];

		// bp conversion required by the brief: 0.5 → 5000 (INTEGER column)
		$this->assertSame(5000, $this->fetchThresholdBp($ruleId));

		$rule = $this->ruleService->getRule($ruleId);
		$this->assertSame(0.5, $rule['threshold']);
		$this->assertSame(5000, $rule['thresholdBp']);
	}

	public function testCreateRuleAcceptsExplicitNullThreshold(): void {
		$r = $this->createRule(null);
		$this->assertArrayHasKey('id', $r);
		$ruleId = (int)$r['id'];

		$this->assertNull($this->fetchThresholdBp($ruleId));
		$rule = $this->ruleService->getRule($ruleId);
		$this->assertNull($rule['threshold']);
		$this->assertNull($rule['thresholdBp']);
	}

	public function testThresholdRoundTripsThroughFourDecimalFloatSurface(): void {
		$r = $this->createRule(0.3333);
		$this->assertArrayHasKey('id', $r);
		$ruleId = (int)$r['id'];

		// write: round(0.3333 * 10000) = 3333; read: round(3333 / 10000.0, 4)
		$this->assertSame(3333, $this->fetchThresholdBp($ruleId));
		$rule = $this->ruleService->getRule($ruleId);
		$this->assertSame(0.3333, $rule['threshold']);
		$this->assertSame(3333, $rule['thresholdBp']);
	}

	public function testSaveRuleRejectsZeroThresholdAndKeepsStoredValue(): void {
		$r = $this->createRule(0.5);
		$this->assertArrayHasKey('id', $r);
		$ruleId = (int)$r['id'];

		$saved = $this->ruleService->saveRule(
			$ruleId,
			$this->idTagPending,
			$this->idTagApproved,
			$this->idTagRejected,
			[['entityId' => 'user1', 'type' => 'user']],
			[['entityId' => 'user1', 'type' => 'user']],
			'threshold rule',
			false,
			0.0
		);
		$this->assertArrayHasKey('error', $saved);
		$this->assertSame('Invalid threshold', $saved['error']);

		// no partial write: the previously stored bp survives the failed save
		$this->assertSame(5000, $this->fetchThresholdBp($ruleId));
		$rule = $this->ruleService->getRule($ruleId);
		$this->assertSame(0.5, $rule['threshold']);
	}

	public function testSaveRuleUpdatesThenClearsThreshold(): void {
		$r = $this->createRule(0.5);
		$this->assertArrayHasKey('id', $r);
		$ruleId = (int)$r['id'];

		$saved = $this->ruleService->saveRule(
			$ruleId,
			$this->idTagPending,
			$this->idTagApproved,
			$this->idTagRejected,
			[['entityId' => 'user1', 'type' => 'user']],
			[['entityId' => 'user1', 'type' => 'user']],
			'threshold rule',
			false,
			1.0
		);
		$this->assertSame(['id' => $ruleId], $saved);
		$this->assertSame(10000, $this->fetchThresholdBp($ruleId));
		$this->assertSame(1.0, $this->ruleService->getRule($ruleId)['threshold']);

		// clearing writes an explicit NULL binding (§3.2: PARAM_NULL)
		$saved = $this->ruleService->saveRule(
			$ruleId,
			$this->idTagPending,
			$this->idTagApproved,
			$this->idTagRejected,
			[['entityId' => 'user1', 'type' => 'user']],
			[['entityId' => 'user1', 'type' => 'user']],
			'threshold rule',
			false,
			null
		);
		$this->assertSame(['id' => $ruleId], $saved);
		$this->assertNull($this->fetchThresholdBp($ruleId));
		$this->assertNull($this->ruleService->getRule($ruleId)['threshold']);
	}

	public function testGetVotesLaterRowWinsAndListsStayDisjoint(): void {
		$r = $this->createRule(0.5);
		$this->assertArrayHasKey('id', $r);
		$ruleId = (int)$r['id'];

		// a request cycle stores a PENDING row (legacy storeAction path)
		$this->ruleService->storeAction(self::FILE_ID, $ruleId, 'user1', Application::STATE_PENDING);
		// append-only votes: user1 approves first, then rejects. All rows land
		// in the same second in practice, so the id ASC tiebreaker must let the
		// later row win; a tick-over only reinforces it via timestamp ASC.
		$this->ruleService->recordVote(self::FILE_ID, $ruleId, 'user1', Application::STATE_APPROVED);
		$this->ruleService->recordVote(self::FILE_ID, $ruleId, 'user1', Application::STATE_REJECTED);
		$this->ruleService->recordVote(self::FILE_ID, $ruleId, 'user2', Application::STATE_APPROVED);

		// recordVote is append-only: nothing was deleted (PENDING row and both
		// of user1's votes remain in the table)
		$this->assertSame(
			[
				Application::STATE_PENDING,
				Application::STATE_APPROVED,
				Application::STATE_REJECTED,
				Application::STATE_APPROVED,
			],
			$this->fetchActivityStates($ruleId)
		);

		$votes = $this->ruleService->getVotes(self::FILE_ID, $ruleId);

		// PENDING rows ignored; the later REJECTED row overrode user1's
		// APPROVED vote (timestamp ASC, id ASC, latest-wins overwrite)
		$this->assertSame(['user2'], $votes['approved']);
		$this->assertSame(['user1'], $votes['rejected']);
		// one state per user by construction: the two lists are disjoint
		$this->assertSame([], array_intersect($votes['approved'], $votes['rejected']));
	}

	public function testWasApprovedAfterReadsLatestApprovedRow(): void {
		$r = $this->createRule(0.5);
		$this->assertArrayHasKey('id', $r);
		$ruleId = (int)$r['id'];

		// no APPROVED row yet → legacy early return true
		$this->assertTrue($this->ruleService->wasApprovedAfter(self::FILE_ID, 1500));

		// an older INTERMEDIATE approval, the FINAL approval, and a newer
		// rejection that the state filter must ignore (consumer adaptation A)
		$this->insertActivityRow($ruleId, 'user1', Application::STATE_APPROVED, 1000);
		$this->insertActivityRow($ruleId, 'user2', Application::STATE_APPROVED, 2000);
		$this->insertActivityRow($ruleId, 'user2', Application::STATE_REJECTED, 4000);

		// latest APPROVED = 2000 → 2000 < 1500 is false. Legacy first-row
		// semantics would read 1000 and answer true.
		$this->assertFalse($this->ruleService->wasApprovedAfter(self::FILE_ID, 1500));
		// 2000 < 2500 → true
		$this->assertTrue($this->ruleService->wasApprovedAfter(self::FILE_ID, 2500));
		// the REJECTED@4000 row must not be considered: the correct answer
		// reads 2000 < 3000 → true, while a state-blind "latest row" would
		// read 4000 < 3000 → false
		$this->assertTrue($this->ruleService->wasApprovedAfter(self::FILE_ID, 3000));
	}

	/**
	 * @param float|null $threshold ratio surface value, null = legacy rule
	 * @param bool $passThresholdArg false = call with the legacy 7 positional args only
	 * @return array<string, mixed>
	 */
	private function createRule(?float $threshold, bool $passThresholdArg = true): array {
		$approvers = [['entityId' => 'user1', 'type' => 'user']];
		$requesters = [['entityId' => 'user1', 'type' => 'user']];
		if ($passThresholdArg) {
			$r = $this->ruleService->createRule(
				$this->idTagPending,
				$this->idTagApproved,
				$this->idTagRejected,
				$approvers,
				$requesters,
				'threshold rule',
				false,
				$threshold
			);
		} else {
			$r = $this->ruleService->createRule(
				$this->idTagPending,
				$this->idTagApproved,
				$this->idTagRejected,
				$approvers,
				$requesters,
				'threshold rule',
				false
			);
		}
		if (isset($r['id'])) {
			$this->createdRuleIds[] = (int)$r['id'];
		}
		return $r;
	}

	private function fetchThresholdBp(int $ruleId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('approval_threshold_bp')
			->from('approval_rules')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($ruleId, IQueryBuilder::PARAM_INT)));
		$req = $qb->executeQuery();
		$value = $req->fetchOne();
		$req->closeCursor();
		if ($value === false || $value === null) {
			return null;
		}
		return (int)$value;
	}

	/**
	 * @return int[] new_state values ordered by row id
	 */
	private function fetchActivityStates(int $ruleId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('new_state')
			->from('approval_activity')
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter(self::FILE_ID, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('rule_id', $qb->createNamedParameter($ruleId, IQueryBuilder::PARAM_INT)));
		$qb->orderBy('id', 'ASC');
		$req = $qb->executeQuery();
		$rows = $req->fetchAll();
		$req->closeCursor();
		return array_map(static fn (array $row): int => (int)$row['new_state'], $rows);
	}

	private function insertActivityRow(int $ruleId, string $userId, int $state, int $timestamp): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('approval_activity')
			->values([
				'file_id' => $qb->createNamedParameter(self::FILE_ID, IQueryBuilder::PARAM_INT),
				'rule_id' => $qb->createNamedParameter($ruleId, IQueryBuilder::PARAM_INT),
				'user_id' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
				'new_state' => $qb->createNamedParameter($state, IQueryBuilder::PARAM_INT),
				'timestamp' => $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT),
				'message' => $qb->createNamedParameter('', IQueryBuilder::PARAM_STR),
			]);
		$qb->executeStatement();
	}
}
