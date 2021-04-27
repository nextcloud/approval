<?php
/**
 * Nextcloud - Approval
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2021
 */

namespace OCA\Approval\Service;

use OCP\IL10N;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use OCP\SystemTag\TagAlreadyExistsException;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;
use OCP\IUserManager;
use OCP\IUser;
use OCP\IGroupManager;
use OCP\App\IAppManager;
use OCP\Notification\IManager as INotificationManager;

use OCA\Approval\AppInfo\Application;
use OCA\Approval\Activity\ActivityManager;

class ApprovalService {
	private $l10n;
	private $logger;

	/**
	 * Service to operate on tags
	 */
	public function __construct(string $appName,
								IConfig $config,
								LoggerInterface $logger,
								ISystemTagManager $tagManager,
								ISystemTagObjectMapper $tagObjectMapper,
								IRootFolder $root,
								IUserManager $userManager,
								IGroupManager $groupManager,
								IAppManager $appManager,
								INotificationManager $notificationManager,
								RuleService $ruleService,
								ActivityManager $activityManager,
								IL10N $l10n) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->config = $config;
		$this->root = $root;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->appManager = $appManager;
		$this->notificationManager = $notificationManager;
		$this->activityManager = $activityManager;
		$this->tagManager = $tagManager;
		$this->tagObjectMapper = $tagObjectMapper;
		$this->ruleService = $ruleService;
	}

	/**
	 * @param string $name of the new tag
	 * @return array
	 */
	public function createTag(string $name): array {
		try {
			$this->tagManager->createTag($name, false, false);
			return [];
		} catch (TagAlreadyExistsException $e) {
			return ['error' => 'Tag already exists'];
		}
	}

	/**
	 * Get rules allowing user to request approval
	 */
	public function getUserRequesterRules(string $userId): array {
		$userRules = [];
		$rules = $this->ruleService->getRules();
		foreach ($rules as $rule) {
			if ($this->userIsAuthorizedByRule($userId, $rule, 'requesters')) {
				$userRules[] = $rule;
			}
		}
		return $userRules;
	}

	/**
	 * Check if user has access to a given file
	 *
	 * @param int $fileId
	 * @param string|null $userId
	 * @return bool
	 */
	public function userHasAccessTo(int $fileId, ?string $userId): bool {
		$user = $this->userManager->get($userId);
		if ($user instanceof IUser) {
			$userFolder = $this->root->getUserFolder($userId);
			$found = $userFolder->getById($fileId);
			return count($found) > 0;
		}
		return false;
	}

	/**
	 * Check if a user is authorized to approve or request by a given rule
	 *
	 * @param string $userId
	 * @param array $rule
	 * @param string $role
	 * @return bool
	 */
	private function userIsAuthorizedByRule(string $userId, array $rule, string $role = 'approvers'): bool {
		$circlesEnabled = $this->appManager->isEnabledForUser('circles');

		$user = $this->userManager->get($userId);

		$ruleUserIds = array_map(function ($w) {
			return $w['entityId'];
		}, array_filter($rule[$role], function ($w) {
			return $w['type'] === 'user';
		}));

		// if user is in rule's user list
		if (in_array($userId, $ruleUserIds)) {
			return true;
		} else {
			// if user is member of one rule's group list
			$ruleGroupIds = array_map(function ($w) {
				return $w['entityId'];
			}, array_filter($rule[$role], function ($w) {
				return $w['type'] === 'group';
			}));
			foreach ($ruleGroupIds as $groupId) {
				if ($this->groupManager->groupExists($groupId) && $this->groupManager->get($groupId)->inGroup($user)) {
					return true;
				}
			}
			// if user is member of one rule's circle list
			if ($circlesEnabled) {
				$ruleCircleIds = array_map(function ($w) {
					return $w['entityId'];
				}, array_filter($rule[$role], function ($w) {
					return $w['type'] === 'circle';
				}));
				foreach ($ruleCircleIds as $circleId) {
					if ($this->isUserInCircle($userId, $circleId)) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Check if a user is in a given circle
	 *
	 * @param string $userId
	 * @param string $circleId
	 * @return bool
	 */
	private function isUserInCircle(string $userId, string $circleId): bool {
		$circleDetails = null;
		try {
			$circleDetails = \OCA\Circles\Api\v1\Circles::detailsCircle($circleId);
		} catch (\OCA\Circles\Exceptions\CircleDoesNotExistException $e) {
			return false;
		}
		// is the circle owner
		if ($circleDetails->getOwner()->getUserId() === $userId) {
			return true;
		} else {
			if ($circleDetails->getMembers() !== null) {
				foreach ($circleDetails->getMembers() as $m) {
					// is member of this circle
					if ($m->getUserId() === $userId) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Get approval state of a given file for a given user
	 * @param int $fileId
	 * @param string|null $userId
	 * @return int
	 */
	public function getApprovalState(int $fileId, ?string $userId): int {
		if (is_null($userId) || !$this->userHasAccessTo($fileId, $userId)) {
			return Application::STATE_NOTHING;
		}

		$rules = $this->ruleService->getRules();

		// first check if it's approvable
		foreach ($rules as $id => $rule) {
			try {
				if ($this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagPending'])
					&& $this->userIsAuthorizedByRule($userId, $rule, 'approvers')) {
					return Application::STATE_APPROVABLE;
				}
			} catch (TagNotFoundException $e) {
			}
		}

		// then check other states
		foreach ($rules as $id => $rule) {
			try {
				if ($this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagPending'])) {
					return Application::STATE_PENDING;
				}
			} catch (TagNotFoundException $e) {
			}
		}
		foreach ($rules as $id => $rule) {
			try {
				if ($this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagApproved'])) {
					return Application::STATE_APPROVED;
				} elseif ($this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagRejected'])) {
					return Application::STATE_REJECTED;
				}
			} catch (TagNotFoundException $e) {
			}
		}

		return Application::STATE_NOTHING;
	}

	/**
	 * Approve a file
	 *
	 * @param int $fileId
	 * @param string|null $userId
	 * @return bool success
	 */
	public function approve(int $fileId, ?string $userId): bool {
		$fileState = $this->getApprovalState($fileId, $userId);
		// if file has pending tag and user is authorized to approve it
		if ($fileState === Application::STATE_APPROVABLE) {
			$rules = $this->ruleService->getRules();
			foreach ($rules as $ruleId => $rule) {
				try {
					if ($this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagPending'])
						&& $this->userIsAuthorizedByRule($userId, $rule, 'approvers')) {
						$this->tagObjectMapper->assignTags($fileId, 'files', $rule['tagApproved']);
						$this->tagObjectMapper->unassignTags($fileId, 'files', $rule['tagPending']);

						// store activity in our tables
						$this->ruleService->storeAction($fileId, $ruleId, $userId, Application::STATE_APPROVED);

						$this->sendApprovalNotification($fileId, $userId, true);
						$this->activityManager->triggerEvent(
							ActivityManager::APPROVAL_OBJECT_NODE, $fileId,
							ActivityManager::SUBJECT_APPROVED,
							[]
						);
						return true;
					}
				} catch (TagNotFoundException $e) {
				}
			}
		}
		return false;
	}

	/**
	 * Reject a file
	 *
	 * @param int $fileId
	 * @param string|null $userId
	 * @return bool success
	 */
	public function reject(int $fileId, ?string $userId): bool {
		$fileState = $this->getApprovalState($fileId, $userId);
		// if file has pending tag and user is authorized to approve it
		if ($fileState === Application::STATE_APPROVABLE) {
			$rules = $this->ruleService->getRules();
			foreach ($rules as $ruleId => $rule) {
				try {
					if ($this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagPending'])
						&& $this->userIsAuthorizedByRule($userId, $rule, 'approvers')) {
						$this->tagObjectMapper->assignTags($fileId, 'files', $rule['tagRejected']);
						$this->tagObjectMapper->unassignTags($fileId, 'files', $rule['tagPending']);

						// store activity in our tables
						$this->ruleService->storeAction($fileId, $ruleId, $userId, Application::STATE_REJECTED);

						$this->sendApprovalNotification($fileId, $userId, false);
						$this->activityManager->triggerEvent(
							ActivityManager::APPROVAL_OBJECT_NODE, $fileId,
							ActivityManager::SUBJECT_REJECTED,
							[]
						);
						return true;
					}
				} catch (TagNotFoundException $e) {
				}
			}
		}
		return false;
	}

	/**
	 * Request approval with a given rule
	 *
	 * @param int $fileId
	 * @param int $ruleId
	 * @param string|null $userId
	 * @return array potential error message
	 */
	public function request(int $fileId, int $ruleId, ?string $userId): array {
		$fileState = $this->getApprovalState($fileId, $userId);
		if ($fileState === Application::STATE_NOTHING) {
			$rule = $this->ruleService->getRule($ruleId);
			if (is_null($rule)) {
				return ['error' => 'Rule does not exist'];
			}
			if ($this->userIsAuthorizedByRule($userId, $rule, 'requesters')) {
				$this->tagObjectMapper->assignTags($fileId, 'files', $rule['tagPending']);
				return [];
			} else {
				return ['error' => 'You are not authorized to request with this rule'];
			}
		} else {
			return ['error' => 'File is already pending/approved/rejected'];
		}
	}

	/**
	 * Send approval notifications for a given file to all users having access to it.
	 *
	 * @param int $fileId
	 * @param string|null $approverId
	 * @param bool $approved
	 * @return void
	 */
	private function sendApprovalNotification(int $fileId, ?string $approverId, bool $approved): void {
		$paramsByUser = [];
		$root = $this->root;
		// notification for eveyone having access except the one approving/rejecting
		$this->userManager->callForSeenUsers(function (IUser $user) use ($root, $fileId, $approverId, &$paramsByUser) {
			$thisUserId = $user->getUID();
			if ($thisUserId !== $approverId) {
				$userFolder = $root->getUserFolder($thisUserId);
				$found = $userFolder->getById($fileId);
				if (count($found) > 0) {
					$node = $found[0];
					$path = $userFolder->getRelativePath($node->getPath());
					$type = $node->getType() === FileInfo::TYPE_FILE
						? 'file'
						: 'folder';
					$paramsByUser[$thisUserId] = [
						'type' => $type,
						'fileId' => $fileId,
						'fileName' => $node->getName(),
						'relativePath' => $path,
						'approverId' => $approverId,
					];
				}
			}
		});

		foreach ($paramsByUser as $userId => $params) {
			$manager = $this->notificationManager;
			$notification = $manager->createNotification();

			$subject = $approved ? 'approved' : 'rejected';
			$notification->setApp(Application::APP_ID)
				->setUser($userId)
				->setDateTime(new \DateTime())
				->setObject('dum', 'dum')
				->setSubject($subject, $params);

			$manager->notify($notification);
		}
	}

	/**
	 * Get ids of users authorized to approve or request by a given rule
	 *
	 * @param array $rule
	 * @param string $role
	 * @return array userId list
	 */
	public function getRuleAuthorizedUserIds(array $rule, string $role = 'approvers'): array {
		$circlesEnabled = $this->appManager->isEnabledForUser('circles');

		$ruleUserIds = [];
		foreach ($rule[$role] as $approver) {
			if ($approver['type'] === 'user') {
				if (!in_array($approver['entityId'], $ruleUserIds)) {
					$ruleUserIds[] = $approver['entityId'];
				}
			} elseif ($approver['type'] === 'group') {
				$groupId = $approver['entityId'];
				if ($this->groupManager->groupExists($groupId)) {
					$users = $this->groupManager->get($groupId)->getUsers();
					foreach ($users as $user) {
						if ($user instanceof IUser && !in_array($user->getUID(), $ruleUserIds)) {
							$ruleUserIds[] = $user->getUID();
						}
					}
				}
			} elseif ($circlesEnabled && $approver['type'] === 'circle') {
				$circleId = $approver['entityId'];
				$circleDetails = null;
				try {
					$circleDetails = \OCA\Circles\Api\v1\Circles::detailsCircle($circleId);
				}
				catch (\OCA\Circles\Exceptions\CircleDoesNotExistException $e) {
				}
				if ($circleDetails) {
					$circleMembers = $circleDetails->getMembers();
					if ($circleMembers !== null) {
						foreach ($circleMembers as $member) {
							$userId = $member->getUserId();
							if (!in_array($userId, $ruleUserIds)) {
								$ruleUserIds[] = $userId;
							}
						}
					}
				}
			}
		}
		return $ruleUserIds;
	}

	/**
	 * Send notifications when a file approval is requested
	 * Send it to all users who are authrorized to approve it
	 *
	 * @param int $fileId
	 * @param array $tags
	 * @return void
	 */
	public function sendRequestNotification(int $fileId, array $tags): void {
		// find users involved in rules matching tags
		$rulesUserIds = [];
		$rules = $this->ruleService->getRules();
		foreach ($rules as $id => $rule) {
			// rule matches tags
			if (in_array($rule['tagPending'], $tags)) {
				$thisRuleUserIds = $this->getRuleAuthorizedUserIds($rule, 'approvers');
				foreach ($thisRuleUserIds as $userId) {
					if (!in_array($userId, $rulesUserIds)) {
						$rulesUserIds[] = $userId;
					}
				}
				// create activity (which deals with access checks)
				$this->activityManager->triggerEvent(
					ActivityManager::APPROVAL_OBJECT_NODE, $fileId,
					ActivityManager::SUBJECT_REQUESTED,
					['users' => $thisRuleUserIds]
				);
			}
		}
		// only notify users having access to the file
		$paramsByUser = [];
		$root = $this->root;
		foreach ($rulesUserIds as $userId) {
			$userFolder = $root->getUserFolder($userId);
			$found = $userFolder->getById($fileId);
			if (count($found) > 0) {
				$node = $found[0];
				$path = $userFolder->getRelativePath($node->getPath());
				$type = $node->getType() === FileInfo::TYPE_FILE
					? 'file'
					: 'folder';
				$paramsByUser[$userId] = [
					'type' => $type,
					'fileId' => $fileId,
					'fileName' => $node->getName(),
					'relativePath' => $path,
				];
			}
		}

		// actually send the notifications
		foreach ($paramsByUser as $userId => $params) {
			$manager = $this->notificationManager;
			$notification = $manager->createNotification();

			$subject = 'request';
			$notification->setApp(Application::APP_ID)
				->setUser($userId)
				->setDateTime(new \DateTime())
				->setObject('dum', 'dum')
				->setSubject($subject, $params);

			$manager->notify($notification);
		}
	}
}
