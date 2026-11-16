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
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IRootFolder;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\IManager as IShareManager;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use Psr\Log\LoggerInterface;

/**
 * End-to-end ratio-mode flow on the real container (DESIGN-BRIEF.md v2 §6
 * item 2, database side): request → approve runs the frozen final tail —
 * PENDING row deleted, APPROVED vote row retained, pending tag swapped for
 * the approved tag, event dispatched with the requester and BEFORE the tag
 * mutation.
 *
 * The rule uses a single live approver, so the first vote already reaches
 * any valid bp (n = 1 invariant) — this keeps the flow deterministic in the
 * existing harness, whose group-share visibility is known to be flaky (see
 * the TODO in ApprovalServiceTest::testApproval). The intermediate (silent)
 * multi-vote stage is covered by the mocked ApprovalServiceRatioModeTest.
 */
class ApprovalServiceRatioFlowTest extends TestCase {
	private const USER = 'ratioFlowUser';

	private ApprovalService $approvalService;
	private RuleService $ruleService;
	private UtilsService $utilsService;
	private ISystemTagObjectMapper $tagObjectMapper;
	private IRootFolder $root;
	private IDBConnection $db;
	// defaults guard tearDown() when setUp() aborts mid-way (leftover tags)
	private int $idTagPending = 0;
	private int $idTagApproved = 0;
	private int $idTagRejected = 0;
	private int $idRule = 0;

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();
		$userManager = $c->get(IUserManager::class);
		if ($userManager->get(self::USER) === null) {
			$userManager->createUser(self::USER, 'R0R0R0R0R0');
		}
	}

	protected function setUp(): void {
		parent::setUp();

		$app = new Application();
		$c = $app->getContainer();
		$this->db = $c->get(IDBConnection::class);
		$this->root = $c->get(IRootFolder::class);
		$this->tagObjectMapper = $c->get(ISystemTagObjectMapper::class);
		$this->utilsService = new UtilsService(
			'approval',
			$c->get(IUserManager::class),
			$c->get(IShareManager::class),
			$c->get(IRootFolder::class),
			$c->get(ISystemTagManager::class)
		);
		$this->ruleService = new RuleService(
			'approval',
			$c->get(IDBConnection::class),
			$c->get(IUserManager::class),
			$c->get(IAppManager::class),
			$c->get(ICacheFactory::class)
		);
		$this->approvalService = new ApprovalService(
			'approval',
			$c->get(ISystemTagObjectMapper::class),
			$c->get(IRootFolder::class),
			$c->get(IUserManager::class),
			$c->get(IGroupManager::class),
			$c->get(IAppManager::class),
			$c->get(INotificationManager::class),
			$this->ruleService,
			$c->get(ActivityManager::class),
			$this->utilsService,
			$c->get(IShareManager::class),
			$c->get(IL10N::class),
			$c->get(LoggerInterface::class),
			self::USER,
			$c->get(IUserMountCache::class),
			$c->get(IEventDispatcher::class)
		);

		$r = $this->utilsService->createTag('rflowPending');
		$this->assertArrayHasKey('id', $r, 'tag rflowPending already exists (leftover run?)');
		$this->idTagPending = $r['id'];
		$r = $this->utilsService->createTag('rflowApproved');
		$this->assertArrayHasKey('id', $r, 'tag rflowApproved already exists (leftover run?)');
		$this->idTagApproved = $r['id'];
		$r = $this->utilsService->createTag('rflowRejected');
		$this->assertArrayHasKey('id', $r, 'tag rflowRejected already exists (leftover run?)');
		$this->idTagRejected = $r['id'];

		// ratio-mode rule (threshold 0.5 ⇔ bp 5000), single live approver:
		// with n = 1 the first vote reaches any valid bp (frozen invariant)
		$r = $this->ruleService->createRule(
			$this->idTagPending,
			$this->idTagApproved,
			$this->idTagRejected,
			[['entityId' => self::USER, 'type' => 'user']],
			[['entityId' => self::USER, 'type' => 'user']],
			'ratio flow rule',
			false,
			0.5
		);
		$this->assertArrayHasKey('id', $r, 'rule creation failed');
		$this->idRule = (int)$r['id'];
	}

	protected function tearDown(): void {
		$this->ruleService->deleteRule($this->idRule);
		$this->utilsService->deleteTag($this->idTagPending);
		$this->utilsService->deleteTag($this->idTagApproved);
		$this->utilsService->deleteTag($this->idTagRejected);
		parent::tearDown();
	}

	public function testRatioApproveFinalTailDeletesPendingRowKeepsVoteAndDispatchesBeforeTagChange(): void {
		$uf = $this->root->getUserFolder(self::USER);
		$file = $uf->newFile('ratioFlow.txt', 'content');
		$fileId = $file->getId();

		$events = [];
		$tagStateAtApprovedDispatch = [];
		$dispatcher = (new Application())->getContainer()->get(IEventDispatcher::class);
		$listener = function (ApprovalStateChangedEvent $event) use (&$events, &$tagStateAtApprovedDispatch, $fileId): void {
			$events[] = $event;
			if ($event->getNewState() === Application::STATE_APPROVED) {
				// snapshot the tag state at dispatch time: the tail must fire
				// the event BEFORE mutating tags (§3.3 step 6c before 6d)
				$tagStateAtApprovedDispatch = [
					'pending' => $this->tagObjectMapper->haveTag((string)$fileId, 'files', (string)$this->idTagPending),
					'approved' => $this->tagObjectMapper->haveTag((string)$fileId, 'files', (string)$this->idTagApproved),
				];
			}
		};
		$dispatcher->addListener(ApprovalStateChangedEvent::class, $listener);
		try {
			// 1. request → PENDING row + pending tag + PENDING event
			$result = $this->approvalService->request($fileId, $this->idRule, self::USER, false);
			$this->assertArrayNotHasKey('error', $result);
			$this->assertArrayNotHasKey('warning', $result);
			$this->assertSame([Application::STATE_PENDING], $this->fetchActivityStates($fileId));
			$this->assertTrue($this->tagObjectMapper->haveTag((string)$fileId, 'files', (string)$this->idTagPending));
			$this->assertCount(1, $events);
			$this->assertSame(Application::STATE_PENDING, $events[0]->getNewState());
			$this->assertSame(self::USER, $events[0]->getRequesterUserId());

			// 2. approve → ratio path (bp5000, n = 1) finalizes on this vote
			$approved = $this->approvalService->approve($fileId, self::USER, $file->getEtag());
			$this->assertTrue($approved);

			// PENDING row deleted, APPROVED vote row retained — the key
			// difference from a legacy storeAction() (delete-all) tail would
			// only show with >1 vote row, which the mocked class pins via
			// storeAction-never + captured recordVote rows
			$this->assertSame([Application::STATE_APPROVED], $this->fetchActivityStates($fileId));

			// pending tag swapped for the approved tag (and nothing else)
			$this->assertFalse($this->tagObjectMapper->haveTag((string)$fileId, 'files', (string)$this->idTagPending));
			$this->assertTrue($this->tagObjectMapper->haveTag((string)$fileId, 'files', (string)$this->idTagApproved));

			// exactly one further event: final state, actor, and the requester
			// read from the PENDING row BEFORE it was deleted (a read after the
			// deletion would yield null here)
			$this->assertCount(2, $events);
			$approvedEvent = $events[1];
			$this->assertSame(Application::STATE_APPROVED, $approvedEvent->getNewState());
			$this->assertSame(self::USER, $approvedEvent->getActorUserId());
			$this->assertSame(self::USER, $approvedEvent->getRequesterUserId());
			$this->assertSame(
				['pending' => true, 'approved' => false],
				$tagStateAtApprovedDispatch,
				'event must dispatch while the pending tag is still assigned'
			);
		} finally {
			$dispatcher->removeListener(ApprovalStateChangedEvent::class, $listener);
		}
	}

	/**
	 * @return int[] new_state values ordered by row id
	 */
	private function fetchActivityStates(int $fileId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('new_state')
			->from('approval_activity')
			->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('rule_id', $qb->createNamedParameter($this->idRule, IQueryBuilder::PARAM_INT)));
		$qb->orderBy('id', 'ASC');
		$req = $qb->executeQuery();
		$rows = $req->fetchAll();
		$req->closeCursor();
		return array_map(static fn (array $row): int => (int)$row['new_state'], $rows);
	}
}
