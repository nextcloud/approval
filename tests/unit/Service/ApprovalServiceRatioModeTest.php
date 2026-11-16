<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Service;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Approval\Activity\ActivityManager;
use OCA\Approval\AppInfo\Application;
use OCA\Approval\Events\ApprovalStateChangedEvent;
use OCP\App\IAppManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\IManager as IShareManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use Psr\Log\LoggerInterface;

/**
 * Ratio-mode branch coverage for ApprovalService (DESIGN-BRIEF.md v2 §6
 * items 2, 3, 4, 5 plus own-outcome-only finalization): every collaborator
 * is mocked so vote persistence, tag mutation, event dispatch and — most
 * importantly — the exact operation ORDER of the frozen final tail
 * (§3.3 step 6: getLastAction → delete PENDING rows → dispatch → tag swap →
 * notify → activity) can be asserted directly.
 *
 * DB-level effects of the tail (PENDING row actually deleted, vote rows
 * actually retained) are asserted in ApprovalServiceRatioFlowTest against
 * the real schema; here "no storeAction() from the ratio path" plus the
 * captured recordVote() rows pin the same contract from the collaborator
 * side.
 */
class ApprovalServiceRatioModeTest extends TestCase {
	private const FILE_ID = 123;
	private const ETAG = 'etag-123';
	private const RULE_ID = 1;
	private const TAG_PENDING = '10';
	private const TAG_APPROVED = '11';
	private const TAG_REJECTED = '12';

	private RuleService $ruleService;
	private ApprovalService $approvalService;
	private ISystemTagObjectMapper $tagObjectMapper;
	private array $currentRule;
	/** @var array{approved: string[], rejected: string[]} live vote store */
	private array $votes = ['approved' => [], 'rejected' => []];
	/** @var string[] ordered log of collaborator operations */
	private array $opLog = [];
	/** @var array<int, array> recordVote() calls with arguments */
	private array $recordedVotes = [];
	/** @var array<int, array> storeAction() calls with arguments */
	private array $storedActions = [];
	/** @var ApprovalStateChangedEvent[] */
	private array $dispatchedEvents = [];
	/** @var array<int, array{0: string, 1: string, 2: string}> */
	private array $assignedTags = [];
	/** @var array<int, array{0: string, 1: string, 2: string}> */
	private array $unassignedTags = [];
	/** @var array<int, array{0: string, 1: int, 2: string}> */
	private array $activityEvents = [];

	protected function setUp(): void {
		parent::setUp();

		$this->votes = ['approved' => [], 'rejected' => []];
		$this->opLog = [];
		$this->recordedVotes = [];
		$this->storedActions = [];
		$this->dispatchedEvents = [];
		$this->assignedTags = [];
		$this->unassignedTags = [];
		$this->activityEvents = [];
		$this->currentRule = $this->buildRule(1.0);

		$this->tagObjectMapper = $this->createMock(ISystemTagObjectMapper::class);
		$this->tagObjectMapper->method('getTagIdsForObjects')
			->willReturnCallback(function (array $objectIds, string $objectType): array {
				$tags = [];
				foreach ($objectIds as $objectId) {
					$tags[(int)$objectId] = [self::TAG_PENDING];
				}
				return $tags;
			});
		$this->tagObjectMapper->method('haveTag')
			->willReturnCallback(static fn ($objectId, $objectType, $tagId): bool => (string)$tagId === self::TAG_PENDING);
		$this->tagObjectMapper->method('assignTags')
			->willReturnCallback(function (string $objectId, string $objectType, $tagIds): void {
				$this->opLog[] = 'assignTags';
				$this->assignedTags[] = [$objectId, $objectType, (string)$tagIds];
			});
		$this->tagObjectMapper->method('unassignTags')
			->willReturnCallback(function (string $objectId, string $objectType, $tagIds): void {
				$this->opLog[] = 'unassignTags';
				$this->unassignedTags[] = [$objectId, $objectType, (string)$tagIds];
			});

		$node = $this->createMock(Node::class);
		$node->method('getEtag')->willReturn(self::ETAG);
		$root = $this->createMock(IRootFolder::class);
		$root->method('getFirstNodeById')->willReturn($node);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn($this->createMock(IUser::class));
		$userManager->method('callForSeenUsers')
			->willReturnCallback(function (callable $callback): void {
				// sendApprovalNotification() is the only caller of
				// callForSeenUsers() on the approve/reject path; the real
				// callback is intentionally not executed (no real users).
				$this->opLog[] = 'notify';
			});

		$groupManager = $this->createMock(IGroupManager::class);
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')->willReturn(false);
		$notificationManager = $this->createMock(INotificationManager::class);

		$activityManager = $this->createMock(ActivityManager::class);
		$activityManager->method('triggerEvent')
			->willReturnCallback(function ($objectType, int $entity, $subject, $additionalParams = [], $author = null): void {
				$this->opLog[] = 'activity';
				$this->activityEvents[] = [$objectType, $entity, $subject];
			});

		$utilsService = $this->createMock(UtilsService::class);
		$utilsService->method('userHasAccessTo')->willReturn(true);

		$shareManager = $this->createMock(IShareManager::class);
		$l10n = $this->createMock(IL10N::class);
		$logger = $this->createMock(LoggerInterface::class);
		$userMountCache = $this->createMock(IUserMountCache::class);

		$this->ruleService = $this->createMock(RuleService::class);
		$this->ruleService->method('getRules')
			->willReturnCallback(fn (): array => [self::RULE_ID => $this->currentRule]);
		$this->ruleService->method('getVotes')
			->willReturnCallback(function (int $fileId, int $ruleId): array {
				$this->opLog[] = 'getVotes';
				return $this->votes;
			});
		$this->ruleService->method('recordVote')
			->willReturnCallback(function (int $fileId, int $ruleId, string $userId, int $newState, string $message = ''): void {
				$this->opLog[] = 'recordVote';
				$this->recordedVotes[] = [$fileId, $ruleId, $userId, $newState, $message];
				if ($newState === Application::STATE_APPROVED) {
					$this->votes['approved'][] = $userId;
				} elseif ($newState === Application::STATE_REJECTED) {
					$this->votes['rejected'][] = $userId;
				}
			});
		$this->ruleService->method('storeAction')
			->willReturnCallback(function (int $fileId, int $ruleId, string $userId, int $newState, string $message = ''): void {
				$this->opLog[] = 'storeAction';
				$this->storedActions[] = [$fileId, $ruleId, $userId, $newState, $message];
			});
		$this->ruleService->method('getLastAction')
			->willReturnCallback(function (int $fileId, int $ruleId, int $newState): array {
				$this->opLog[] = 'getLastAction';
				return ['userId' => 'user1', 'timestamp' => 1000, 'message' => ''];
			});

		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$eventDispatcher->method('dispatchTyped')
			->willReturnCallback(function (object $event): void {
				$this->opLog[] = 'dispatch';
				$this->dispatchedEvents[] = $event;
			});

		$this->approvalService = new ApprovalService(
			'approval',
			$this->tagObjectMapper,
			$root,
			$userManager,
			$groupManager,
			$appManager,
			$notificationManager,
			$this->ruleService,
			$activityManager,
			$utilsService,
			$shareManager,
			$l10n,
			$logger,
			'user1',
			$userMountCache,
			$eventDispatcher
		);
	}

	public function testRatioModeApproveBelowThresholdKeepsPendingTagAndPersistsVote(): void {
		// bp10000 (unanimity), n = 2 → the first approval is intermediate
		$result = $this->approvalService->approve(self::FILE_ID, 'user1', self::ETAG);

		$this->assertTrue($result);
		// step 1 (read votes) happens before step 2 (append the vote row)
		$this->assertLessThan($this->opIndexOf('recordVote'), $this->opIndexOf('getVotes'));
		// exactly one append-only vote row; the legacy delete-all storeAction()
		// must never run from the ratio path (it would wipe accumulated votes)
		$this->assertSame(
			[[self::FILE_ID, self::RULE_ID, 'user1', Application::STATE_APPROVED, '']],
			$this->recordedVotes
		);
		$this->assertSame([], $this->storedActions);
		$this->assertSame(['user1'], $this->votes['approved']);
		// pending tag kept: no tag mutation at all
		$this->assertSame([], $this->assignedTags);
		$this->assertSame([], $this->unassignedTags);
		// fully silent intermediate vote (frozen decision 2.3): no event,
		// no notification, no activity, no pending-action lookup
		$this->assertSame([], $this->dispatchedEvents);
		$this->assertSame([], $this->activityEvents);
		$this->assertOpsAbsent(['dispatch', 'notify', 'activity', 'getLastAction']);
		// frozen decision 2.5: the service state payload stays free of
		// threshold/progress/myVote (those are computed in the controller)
		$state = $this->approvalService->getApprovalState(self::FILE_ID, 'user1');
		$this->assertSame(Application::STATE_APPROVABLE, $state['state']);
		$this->assertArrayNotHasKey('threshold', $state);
		$this->assertArrayNotHasKey('progress', $state);
		$this->assertArrayNotHasKey('myVote', $state);
	}

	public function testRatioModeApproveReachingThresholdRunsFinalTailInOrder(): void {
		// first vote stays intermediate and silent
		$this->assertTrue($this->approvalService->approve(self::FILE_ID, 'user1', self::ETAG));
		$this->assertSame([], $this->dispatchedEvents);

		// second approval reaches bp10000 unanimity → final tail
		$this->assertTrue($this->approvalService->approve(self::FILE_ID, 'user2', self::ETAG));

		// steps 1-3 run before the tail: read → append → (re-read), then the tail
		$this->assertLessThan($this->opIndexOf('recordVote'), $this->opIndexOf('getVotes'));
		$this->assertLessThan($this->opIndexOf('getLastAction'), $this->opIndexOf('recordVote'));
		// exact tail order (§3.3 step 6): requester read from the PENDING row →
		// event dispatch BEFORE any tag mutation → tag swap → notify → activity
		$this->assertOpSequence(['getLastAction', 'dispatch', 'assignTags', 'unassignTags', 'notify', 'activity']);
		// both vote rows persisted; no legacy storeAction() (would wipe them)
		$this->assertSame(
			[
				[self::FILE_ID, self::RULE_ID, 'user1', Application::STATE_APPROVED, ''],
				[self::FILE_ID, self::RULE_ID, 'user2', Application::STATE_APPROVED, ''],
			],
			$this->recordedVotes
		);
		$this->assertSame([], $this->storedActions);
		// pending tag swapped for the approved tag (and nothing else)
		$this->assertSame([['123', 'files', self::TAG_APPROVED]], $this->assignedTags);
		$this->assertSame([['123', 'files', self::TAG_PENDING]], $this->unassignedTags);
		// the event carries file, rule, final state, actor and the requester
		// read from the PENDING row before it was deleted
		$this->assertCount(1, $this->dispatchedEvents);
		$event = $this->dispatchedEvents[0];
		$this->assertInstanceOf(ApprovalStateChangedEvent::class, $event);
		$this->assertSame(self::FILE_ID, $event->getFileId());
		$this->assertSame(self::RULE_ID, $event->getRuleId());
		$this->assertSame(Application::STATE_APPROVED, $event->getNewState());
		$this->assertSame('user2', $event->getActorUserId());
		$this->assertSame('user1', $event->getRequesterUserId());
		// activity subject identical to the legacy path
		$this->assertSame(
			[[ActivityManager::APPROVAL_OBJECT_NODE, self::FILE_ID, ActivityManager::SUBJECT_APPROVED]],
			$this->activityEvents
		);
	}

	public function testRatioModeDoubleVoteReturnsFalseWithoutSecondRow(): void {
		// user1 already approved → blocked
		$this->votes = ['approved' => ['user1'], 'rejected' => []];
		$this->assertFalse($this->approvalService->approve(self::FILE_ID, 'user1', self::ETAG));
		// user1 already rejected → blocked from either list
		$this->votes = ['approved' => [], 'rejected' => ['user1']];
		$this->assertFalse($this->approvalService->approve(self::FILE_ID, 'user1', self::ETAG));

		// the duplicate check (step 1) ran, but nothing was written or finalized
		$this->assertContains('getVotes', $this->opLog);
		$this->assertSame([], $this->recordedVotes);
		$this->assertSame([], $this->storedActions);
		$this->assertSame([], $this->assignedTags);
		$this->assertSame([], $this->unassignedTags);
		$this->assertSame([], $this->dispatchedEvents);
		$this->assertOpsAbsent(['recordVote', 'dispatch', 'notify', 'activity', 'getLastAction']);
	}

	public function testRatioModeRejectFiresOnlyWhenVetoRatioExceedsBoundary(): void {
		// bp5000 ⇔ 0.5, n = 2 → reject needs r * 10000 > 10000 ⇔ r > 1
		$this->currentRule = $this->buildRule(0.5);

		// first rejection sits exactly on the boundary → silent intermediate
		$this->assertTrue($this->approvalService->reject(self::FILE_ID, 'user1', self::ETAG));
		$this->assertSame([], $this->dispatchedEvents);
		$this->assertSame([], $this->assignedTags);
		$this->assertSame([], $this->unassignedTags);
		$this->assertSame(
			[[self::FILE_ID, self::RULE_ID, 'user1', Application::STATE_REJECTED, '']],
			$this->recordedVotes
		);

		// second rejection crosses the boundary while approval was never
		// reached (a stays 0 throughout) → final REJECTED tail
		$this->assertTrue($this->approvalService->reject(self::FILE_ID, 'user2', self::ETAG));
		$this->assertSame([], $this->votes['approved']);
		$this->assertOpSequence(['getLastAction', 'dispatch', 'assignTags', 'unassignTags', 'notify', 'activity']);
		$this->assertSame(
			[
				[self::FILE_ID, self::RULE_ID, 'user1', Application::STATE_REJECTED, ''],
				[self::FILE_ID, self::RULE_ID, 'user2', Application::STATE_REJECTED, ''],
			],
			$this->recordedVotes
		);
		$this->assertSame([], $this->storedActions);
		$this->assertSame([['123', 'files', self::TAG_REJECTED]], $this->assignedTags);
		$this->assertSame([['123', 'files', self::TAG_PENDING]], $this->unassignedTags);
		$this->assertCount(1, $this->dispatchedEvents);
		$event = $this->dispatchedEvents[0];
		$this->assertSame(Application::STATE_REJECTED, $event->getNewState());
		$this->assertSame('user2', $event->getActorUserId());
		$this->assertSame('user1', $event->getRequesterUserId());
		$this->assertSame(
			[[ActivityManager::APPROVAL_OBJECT_NODE, self::FILE_ID, ActivityManager::SUBJECT_REJECTED]],
			$this->activityEvents
		);
	}

	public function testRatioModeApproveFinalizesApprovedOutcomeWhenShrinkCreatesCrossOutcome(): void {
		// Denominator shrunk to the single live approver user3 while the votes
		// of departed users user1/user2 still count in a/r (frozen decision
		// 2.6): BOTH conditions hold for bp5000, a = 2, r = 1, n = 1.
		$this->currentRule = $this->buildRule(0.5, [['entityId' => 'user3', 'type' => 'user']]);
		$this->votes = ['approved' => ['user1'], 'rejected' => ['user2']];
		// pins the frozen function's approve-first answer for this state
		$this->assertSame('approved', ApprovalService::evaluateThreshold(2, 1, 1, 5000));

		$this->assertTrue($this->approvalService->approve(self::FILE_ID, 'user3', self::ETAG));

		// own-outcome-only (step 5): the APPROVED finalization runs even though
		// the rejected condition also held — no rejected finalization may fire
		$this->assertSame(
			[[self::FILE_ID, self::RULE_ID, 'user3', Application::STATE_APPROVED, '']],
			$this->recordedVotes
		);
		$this->assertSame([], $this->storedActions);
		$this->assertSame([['123', 'files', self::TAG_APPROVED]], $this->assignedTags);
		$this->assertSame([['123', 'files', self::TAG_PENDING]], $this->unassignedTags);
		foreach ($this->assignedTags as $assigned) {
			$this->assertNotSame(self::TAG_REJECTED, $assigned[2]);
		}
		$this->assertOpSequence(['getLastAction', 'dispatch', 'assignTags', 'unassignTags', 'notify', 'activity']);
		$this->assertCount(1, $this->dispatchedEvents);
		$this->assertSame(Application::STATE_APPROVED, $this->dispatchedEvents[0]->getNewState());
		$this->assertSame('user3', $this->dispatchedEvents[0]->getActorUserId());
	}

	public function testRatioModeApproveDoesNotFinalizeWhenEvaluationYieldsRejected(): void {
		// a = 1, r = 3, n = 4, bp5000 → approve 10000 < 20000 (no),
		// reject 30000 > 20000 (yes) → evaluation yields 'rejected' although
		// this call is an APPROVE: own-outcome-only forbids finalizing.
		$this->currentRule = $this->buildRule(0.5, [
			['entityId' => 'user1', 'type' => 'user'],
			['entityId' => 'user2', 'type' => 'user'],
			['entityId' => 'user3', 'type' => 'user'],
			['entityId' => 'user4', 'type' => 'user'],
		]);
		$this->votes = ['approved' => [], 'rejected' => ['user1', 'user2', 'user4']];
		$this->assertSame('rejected', ApprovalService::evaluateThreshold(1, 3, 4, 5000));

		$this->assertTrue($this->approvalService->approve(self::FILE_ID, 'user3', self::ETAG));

		// the vote is recorded, nothing else happens (step 5 → step 7)
		$this->assertSame(
			[[self::FILE_ID, self::RULE_ID, 'user3', Application::STATE_APPROVED, '']],
			$this->recordedVotes
		);
		$this->assertSame([], $this->storedActions);
		$this->assertSame([], $this->assignedTags);
		$this->assertSame([], $this->unassignedTags);
		$this->assertSame([], $this->dispatchedEvents);
		$this->assertOpsAbsent(['dispatch', 'notify', 'activity', 'getLastAction']);
	}

	public function testLegacyNullThresholdKeepsFirstDecisionWinsPath(): void {
		$this->currentRule = $this->buildRule(null);

		$this->assertTrue($this->approvalService->approve(self::FILE_ID, 'user1', self::ETAG));

		// legacy path never touches the vote machinery
		$this->assertNotContains('getVotes', $this->opLog);
		$this->assertSame([], $this->recordedVotes);
		// legacy storeAction() (delete-all + insert) keeps its exact arguments
		$this->assertSame(
			[[self::FILE_ID, self::RULE_ID, 'user1', Application::STATE_APPROVED, '']],
			$this->storedActions
		);
		$this->assertOpSequence(['getLastAction', 'storeAction', 'dispatch', 'assignTags', 'unassignTags', 'notify', 'activity']);
		$this->assertSame([['123', 'files', self::TAG_APPROVED]], $this->assignedTags);
		$this->assertSame([['123', 'files', self::TAG_PENDING]], $this->unassignedTags);
		$this->assertCount(1, $this->dispatchedEvents);
		$event = $this->dispatchedEvents[0];
		$this->assertSame(Application::STATE_APPROVED, $event->getNewState());
		$this->assertSame('user1', $event->getActorUserId());
		$this->assertSame('user1', $event->getRequesterUserId());
		$this->assertSame(
			[[ActivityManager::APPROVAL_OBJECT_NODE, self::FILE_ID, ActivityManager::SUBJECT_APPROVED]],
			$this->activityEvents
		);
		// legacy rules never grow the new payload keys in the service
		$state = $this->approvalService->getApprovalState(self::FILE_ID, 'user1');
		$this->assertSame(Application::STATE_APPROVABLE, $state['state']);
		$this->assertArrayNotHasKey('threshold', $state);
		$this->assertArrayNotHasKey('progress', $state);
		$this->assertArrayNotHasKey('myVote', $state);
	}

	/**
	 * Build a rule array as RuleService::getRules() exposes it after the v2
	 * backend change (§3.1/§3.3: threshold float|null plus the internal
	 * thresholdBp int|null kept in the payload).
	 *
	 * @param float|null $threshold ratio surface value, null = legacy rule
	 * @param array|null $approvers explicit approver list, defaults to two users
	 */
	private function buildRule(?float $threshold, ?array $approvers = null): array {
		return [
			'id' => self::RULE_ID,
			'tagPending' => self::TAG_PENDING,
			'tagApproved' => self::TAG_APPROVED,
			'tagRejected' => self::TAG_REJECTED,
			'description' => 'ratio rule',
			'approvers' => $approvers ?? [
				['entityId' => 'user1', 'type' => 'user'],
				['entityId' => 'user2', 'type' => 'user'],
			],
			'requesters' => [
				['entityId' => 'user1', 'type' => 'user'],
			],
			'unapproveWhenModified' => false,
			'threshold' => $threshold,
			'thresholdBp' => $threshold === null ? null : (int)round($threshold * 10000),
		];
	}

	private function opIndexOf(string $op): int {
		$index = array_search($op, $this->opLog, true);
		if ($index === false) {
			$this->fail("Expected operation '$op' in log: " . implode(' -> ', $this->opLog));
		}
		return $index;
	}

	/**
	 * Assert that the listed operations happened exactly once each and in the
	 * given relative order (operations not listed are ignored).
	 *
	 * @param string[] $expected
	 */
	private function assertOpSequence(array $expected): void {
		$filtered = array_values(array_filter(
			$this->opLog,
			static fn (string $op): bool => in_array($op, $expected, true)
		));
		$this->assertSame($expected, $filtered, 'Observed operation order: ' . implode(' -> ', $this->opLog));
	}

	/**
	 * @param string[] $ops
	 */
	private function assertOpsAbsent(array $ops): void {
		foreach ($ops as $op) {
			$this->assertNotContains($op, $this->opLog, "Operation '$op' must not run; log: " . implode(' -> ', $this->opLog));
		}
	}
}
