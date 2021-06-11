<?php
/**
 * @copyright Copyright (c) 2020 Matthias Heinisch <nextcloud@matthiasheinisch.de>
 *
 * @author Matthias Heinisch <nextcloud@matthiasheinisch.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Approval\Service;

use OCP\IL10N;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\App\IAppManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\IManager as IShareManager;

use OCP\IDBConnection;
use OCP\SystemTag\ISystemTagManager;

use OCA\Approval\AppInfo\Application;
use OCA\Approval\Activity\ActivityManager;

use ChristophWurst\Nextcloud\Testing\TestCase;

class ApprovalServiceTest extends TestCase {

	/**
	 * @var RuleService
	 */
	private RuleService $ruleService;
	/**
	 * @var ApprovalService
	 */
	private ApprovalService $approvalService;
	/**
	 * @var UtilsService
	 */
	private UtilsService $utilsService;
	/**
	 * @var IRootFolder
	 */
	private IRootFolder $root;
	/**
	 * @var int
	 */
	private int $idTagPending1;
	/**
	 * @var int
	 */
	private $idTagApproved1;
	/**
	 * @var int
	 */
	private $idTagRejected1;
	/**
	 * @var int
	 */
	private $idRule1;

	public static function setUpBeforeClass(): void {
		$app = new Application();
		$c = $app->getContainer();

		// create users
		$userManager = $c->get(IUserManager::class);
		$u1 = $userManager->createUser('user1', 'T0T0T0T0T0');
		$u1->setEMailAddress('toto@toto.net');
		$u2 = $userManager->createUser('user2', 'T0T0T0T0T0');
		$u3 = $userManager->createUser('user3', 'T0T0T0T0T0');
		$groupManager = $c->get(IGroupManager::class);
		$group1 = $groupManager->createGroup('group1');
		$group1->addUser($u1);
		$group2 = $groupManager->createGroup('group2');
		$group2->addUser($u2);
		$group2->addUser($u3);
		// TODO users are not visible in the group => why?
		$users = $group2->getUsers();
	}

	protected function setUp(): void {
		parent::setUp();

		$app = new Application();
		$c = $app->getContainer();
		$this->root = $c->get(IRootFolder::class);

		$this->utilsService = new UtilsService(
			'approval',
			$c->get(IUserManager::class),
			$c->get(IShareManager::class),
			$c->get(IRootFolder::class),
			$c->get(ISystemTagManager::class),
		);
		$this->ruleService = new RuleService(
			'approval',
			$c->get(IDBConnection::class),
			$c->get(IUserManager::class),
			$c->get(IAppManager::class),
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
		);

		// add some tags
		$r = $this->utilsService->createTag('pending1');
		$this->idTagPending1 = $r['id'];
		$r = $this->utilsService->createTag('approved1');
		$this->idTagApproved1 = $r['id'];
		$r = $this->utilsService->createTag('rejected1');
		$this->idTagRejected1 = $r['id'];

		// add a rule
		$approvers = [
			[
				'entityId' => 'user1',
				'type' => 'user',
			],
		];
		$requesters = [
			[
				'entityId' => 'user1',
				'type' => 'user',
			],
		];
		$r = $this->ruleService->createRule(
			$this->idTagPending1, $this->idTagApproved1, $this->idTagRejected1,
			$approvers, $requesters, 'desc1'
		);
		$this->idRule1 = $r['id'];
	}

	protected function tearDown(): void {
		$this->utilsService->deleteTag($this->idTagPending1);
		$this->utilsService->deleteTag($this->idTagApproved1);
		$this->utilsService->deleteTag($this->idTagRejected1);

		$this->ruleService->deleteRule($this->idRule1);
	}

	public function testGetRequesterRules() {
		$result = $this->approvalService->getUserRequesterRules('user1');
		// $this->assertEmpty($result);
		$this->assertCount(1, $result);
		$this->assertEquals('desc1', $result[0]['description']);
	}

	public function testCreateAndDeleteRule() {
		// add some tags
		$r = $this->utilsService->createTag('pending2');
		$idTagPending2 = $r['id'];
		$r = $this->utilsService->createTag('approved2');
		$idTagApproved2 = $r['id'];
		$r = $this->utilsService->createTag('rejected2');
		$idTagRejected2 = $r['id'];

		// add a rule
		$approvers = [
			[
				'entityId' => 'user2',
				'type' => 'user',
			],
		];
		$requesters = [
			[
				'entityId' => 'user2',
				'type' => 'user',
			],
		];
		$r = $this->ruleService->createRule(
			$idTagPending2, $idTagApproved2, $idTagRejected2,
			$approvers, $requesters, 'desc2'
		);
		$this->idRule2 = $r['id'];

		$rule = $this->ruleService->getRule($this->idRule2);
		$this->assertEquals($this->idRule2, $rule['id']);
		$this->assertEquals('desc2', $rule['description']);
		$this->assertEquals($idTagPending2, $rule['tagPending']);
		$this->assertEquals($idTagApproved2, $rule['tagApproved']);
		$this->assertEquals($idTagRejected2, $rule['tagRejected']);

		// delete
		$r = $this->ruleService->deleteRule($this->idRule2);
		$this->assertEmpty($r);
		$rule = $this->ruleService->getRule($this->idRule2);
		$this->assertNull($rule);
	}

	public function testGetRules() {
		$rules = $this->ruleService->getRules();
		$this->assertCount(1, $rules);
		$idRule1 = $this->idRule1;
		$this->assertCount(1, $rules[$idRule1]['approvers']);
		$this->assertEquals('user1', $rules[$idRule1]['approvers'][0]['entityId']);
		$this->assertEquals('user', $rules[$idRule1]['approvers'][0]['type']);
		$this->assertCount(1, $rules[$idRule1]['requesters']);
		$this->assertEquals('user1', $rules[$idRule1]['requesters'][0]['entityId']);
		$this->assertEquals('user', $rules[$idRule1]['requesters'][0]['type']);
		$this->assertEquals('desc1', $rules[$idRule1]['description']);
		$this->assertEquals($this->idTagPending1, $rules[$idRule1]['tagPending']);
		$this->assertEquals($this->idTagApproved1, $rules[$idRule1]['tagApproved']);
		$this->assertEquals($this->idTagRejected1, $rules[$idRule1]['tagRejected']);
	}

	public function testGetRuleAuthorizedUserIds() {
		$rule = $this->ruleService->getRule($this->idRule1);
		$uidApprovers = $this->approvalService->getRuleAuthorizedUserIds($rule, 'approvers');
		$this->assertCount(1, $uidApprovers);
		$this->assertEquals('user1', $uidApprovers[0]);
		$uidRequesters = $this->approvalService->getRuleAuthorizedUserIds($rule, 'requesters');
		$this->assertCount(1, $uidRequesters);
		$this->assertEquals('user1', $uidRequesters[0]);
	}

	public function testGetApprovalState() {
		$uf = $this->root->getUserFolder('user1');
		$file1 = $uf->newFile('file1.txt', 'content');
		$state = $this->approvalService->getApprovalState($file1->getId(), 'user1');
		$this->assertEquals(Application::STATE_NOTHING, $state['state']);

		$state = $this->approvalService->getApprovalState($file1->getId(), 'user2');
		$this->assertEquals(Application::STATE_NOTHING, $state['state']);
	}

	// test request/approve/reject
	public function testApproval() {
		// create a file
		$uf1 = $this->root->getUserFolder('user1');
		$fileToApprove = $uf1->newFile('fileToApprove.txt', 'content');
		$fileToReject = $uf1->newFile('fileToReject.txt', 'content');
		$otherFile = $uf1->newFile('otherFile.txt', 'content');

		// add some tags
		$r = $this->utilsService->createTag('pending3');
		$idTagPending3 = $r['id'];
		$r = $this->utilsService->createTag('approved3');
		$idTagApproved3 = $r['id'];
		$r = $this->utilsService->createTag('rejected3');
		$idTagRejected3 = $r['id'];

		// add a rule
		$approvers = [
			[
				'entityId' => 'user1',
				'type' => 'user',
			],
			[
				'entityId' => 'group2',
				'type' => 'group',
			],
		];
		$requesters = [
			[
				'entityId' => 'user1',
				'type' => 'user',
			],
		];
		$r = $this->ruleService->createRule(
			$idTagPending3, $idTagApproved3, $idTagRejected3,
			$approvers, $requesters, 'desc3'
		);
		$this->idRule3 = $r['id'];

		$stateForUser1 = $this->approvalService->getApprovalState($fileToApprove->getId(), 'user1');
		$this->assertEquals(Application::STATE_NOTHING, $stateForUser1['state']);

		// request
		// TODO find a way to try with a different approver user, the shared access is not effective here
		$this->approvalService->request($fileToApprove->getId(), $this->idRule3, 'user1', true);
		$this->approvalService->request($fileToApprove->getId(), $this->idRule3, 'user1', false);

		$this->approvalService->request($fileToReject->getId(), $this->idRule3, 'user1', true);
		$this->approvalService->request($fileToReject->getId(), $this->idRule3, 'user1', false);

		// request failures
		// file already pending
		$result = $this->approvalService->request($fileToApprove->getId(), $this->idRule3, 'user1', true);
		$this->assertTrue(isset($result['error']));
		// rule does not exist
		$result = $this->approvalService->request($otherFile->getId(), -1, 'user1', true);
		$this->assertTrue(isset($result['error']));
		// unauthorized user
		$result = $this->approvalService->request($otherFile->getId(), $this->idRule3, 'user2', true);
		$this->assertTrue(isset($result['error']));

		// get state
		$stateForUser1 = $this->approvalService->getApprovalState($fileToApprove->getId(), 'user1');
		$this->assertEquals(Application::STATE_APPROVABLE, $stateForUser1['state']);

		// TODO this should return Application::STATE_APPROVABLE because user2 is in group2 which is in approvers list
		// but users are not added in groups here
		$stateForUser2 = $this->approvalService->getApprovalState($fileToApprove->getId(), 'user2');
		$this->assertEquals(Application::STATE_NOTHING, $stateForUser2['state']);
		// this should also return the rule
		$result = $this->approvalService->getUserRequesterRules('user2');
		$this->assertCount(0, $result);
		//$this->assertEquals('desc1', $result[0]['description']);

		// approve
		$this->approvalService->approve($fileToApprove->getId(), 'user1');
		$stateForUser1 = $this->approvalService->getApprovalState($fileToApprove->getId(), 'user1');
		$this->assertEquals(Application::STATE_APPROVED, $stateForUser1['state']);

		// reject
		$this->approvalService->reject($fileToReject->getId(), 'user1');
		$stateForUser1 = $this->approvalService->getApprovalState($fileToReject->getId(), 'user1');
		$this->assertEquals(Application::STATE_REJECTED, $stateForUser1['state']);
	}
}
