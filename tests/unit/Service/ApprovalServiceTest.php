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

use OCA\Approval\Activity\ActivityManager;

use ChristophWurst\Nextcloud\Testing\TestCase;

class ApprovalServiceTest extends TestCase {
	private $service;

	protected function setUp(): void {
		parent::setUp();

		$this->tagObjectMapper = $this->createMock(ISystemTagObjectMapper::class);
		$this->root = $this->createMock(IRootFolder::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->appManager = $this->createMock(IAppManager::class);

		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->ruleService = $this->createMock(RuleService::class);
		$this->utilsService = $this->createMock(UtilsService::class);
		$this->activityManager = $this->createMock(ActivityManager::class);
		$this->shareManager = $this->createMock(IShareManager::class);

		$this->l10n = $this->createMock(IL10N::class);
		$this->service = new ApprovalService(
			'approval',
			$this->tagObjectMapper,
			$this->root,
			$this->userManager,
			$this->groupManager,
			$this->appManager,
			$this->notificationManager,
			$this->ruleService,
			$this->activityManager,
			$this->utilsService,
			$this->shareManager,
			$this->l10n
		);
	}

	public function testGetRequesterRules() {
		$result = $this->service->getUserRequesterRules('mrstest');
		$this->assertEmpty($result);
	}
}
