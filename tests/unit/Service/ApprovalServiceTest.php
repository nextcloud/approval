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

	protected function setUp(): void {
		parent::setUp();

		$this->app = new Application();
		$this->container = $this->app->getContainer();
		$c = $this->container;

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
				'entityId' => 'mrstest',
				'type' => 'user',
			],
		];
		$requesters = [
			[
				'entityId' => 'mrstest',
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
		$result = $this->approvalService->getUserRequesterRules('mrstest');
		// $this->assertEmpty($result);
		$this->assertEquals(count($result), 1);
	}
}
