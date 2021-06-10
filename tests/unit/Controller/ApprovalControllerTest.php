<?php
/**
 * @copyright Copyright (c) 2021 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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

namespace OCA\Approval\Controller;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

use OCA\Approval\Service\UtilsService;
use OCA\Approval\Service\ApprovalService;
use OCA\Approval\Service\RuleService;

class PageControllerTest extends TestCase {
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->utilsService = $this->createMock(UtilsService::class);
		$this->approvalService = $this->createMock(ApprovalService::class);
		$this->ruleService = $this->createMock(RuleService::class);

		$this->controller = new ApprovalController(
			'approval',
			$this->request,
			$this->utilsService,
			$this->approvalService,
			$this->ruleService,
			'mrstest'
		);
	}


	public function testIndex() {
		$user = $this->createMock(IUser::class);
		$user->method('getUid')->willReturn('mrstest');
		$this->userSession->method('getUser')->willReturn($user);

		$result = $this->controller->getUserRequesterRules();

		// $this->assertEquals('main', $result->getTemplateName());
		// $this->assertEquals('user', $result->getRenderAs());
		$this->assertTrue($result instanceof DataResponse);
	}
}
