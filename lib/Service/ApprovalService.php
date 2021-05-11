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

use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Files\Node;
use OCP\Constants;

use OCA\DAV\Connector\Sabre\Node as SabreNode;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;

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
								IShareManager $shareManager,
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
		$this->shareManager = $shareManager;
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
	 * @return array state and rule id
	 */
	public function getApprovalState(int $fileId, ?string $userId): array {
		if (is_null($userId) || !$this->userHasAccessTo($fileId, $userId)) {
			return ['state' => Application::STATE_NOTHING];
		}

		$rules = $this->ruleService->getRules();

		// first check if it's approvable
		foreach ($rules as $id => $rule) {
			try {
				if ($this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagPending'])
					&& $this->userIsAuthorizedByRule($userId, $rule, 'approvers')) {
					return [
						'state' => Application::STATE_APPROVABLE,
						'rule' => $rule,
					];
				}
			} catch (TagNotFoundException $e) {
			}
		}

		// then check pending in priority
		foreach ($rules as $id => $rule) {
			try {
				if ($this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagPending'])) {
					return [
						'state' => Application::STATE_PENDING,
						'rule' => $rule,
					];
				}
			} catch (TagNotFoundException $e) {
			}
		}
		// then rejected
		foreach ($rules as $id => $rule) {
			try {
				if ($this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagRejected'])) {
					return [
						'state' => Application::STATE_REJECTED,
						'rule' => $rule,
					];
				}
			} catch (TagNotFoundException $e) {
			}
		}
		// then approved
		foreach ($rules as $id => $rule) {
			try {
				if ($this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagApproved'])) {
					return [
						'state' => Application::STATE_APPROVED,
						'rule' => $rule,
					];
				}
			} catch (TagNotFoundException $e) {
			}
		}

		return ['state' => Application::STATE_NOTHING];
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
		if ($fileState['state'] === Application::STATE_APPROVABLE) {
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
		if ($fileState['state'] === Application::STATE_APPROVABLE) {
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
	public function request(int $fileId, int $ruleId, ?string $userId, bool $createShares): array {
		$fileState = $this->getApprovalState($fileId, $userId);
		if ($fileState['state'] === Application::STATE_NOTHING) {
			$rule = $this->ruleService->getRule($ruleId);
			if (is_null($rule)) {
				return ['error' => 'Rule does not exist'];
			}
			if ($this->userIsAuthorizedByRule($userId, $rule, 'requesters')) {
				if ($createShares) {
					$this->createShares($fileId, $rule, $userId);
					// if shares are auto created, request is actually done in a separated request with $createShares === false
					return [];
				}
				$this->tagObjectMapper->assignTags($fileId, 'files', $rule['tagPending']);

				// store activity in our tables
				$this->ruleService->storeAction($fileId, $ruleId, $userId, Application::STATE_PENDING);
				// still produce an activity entry
				$this->activityManager->triggerEvent(
					ActivityManager::APPROVAL_OBJECT_NODE, $fileId,
					ActivityManager::SUBJECT_REQUESTED_ORIGIN,
					['origin_user_id' => $userId]
				);

				// check if someone can actually approve
				$ruleUserIds = $this->getRuleAuthorizedUserIds($rule, 'approvers');
				foreach ($ruleUserIds as $uid) {
					if ($this->userHasAccessTo($fileId, $uid)) {
						return [];
					}
				}
				return ['warning' => 'This element is not shared with any user who is authorized to approve it'];
			} else {
				return ['error' => 'You are not authorized to request with this rule'];
			}
		} else {
			return ['error' => 'File is already pending/approved/rejected'];
		}
	}

	/**
	 * Share file with everybody who can approve with given rule and have no access yet
	 */
	private function createShares(int $fileId, array $rule, string $userId): array {
		$createdShares = [];
		// get node
		$userFolder = $this->root->getUserFolder($userId);
		$found = $userFolder->getById($fileId);
		if (count($found) > 0) {
			$node = $found[0];
		} else {
			return [];
		}
		$label = $this->l10n->t('Approval share');

		foreach ($rule['approvers'] as $approver) {
			if ($approver['type'] === 'user' && !$this->userHasAccessTo($fileId, $approver['entityId'])) {
				// create user share
				if ($this->createShare($node, IShare::TYPE_USER, $approver['entityId'], $userId, $label)) {
					$createdShares[] = $approver;
				}
			}
		}
		if ($this->shareManager->allowGroupSharing()) {
			foreach ($rule['approvers'] as $approver) {
				if ($approver['type'] === 'group') {
					if ($this->createShare($node, IShare::TYPE_GROUP, $approver['entityId'], $userId, $label)) {
						$createdShares[] = $approver;
					}
				}
			}
		}

		$circlesEnabled = $this->appManager->isEnabledForUser('circles');
		if ($circlesEnabled) {
			foreach ($rule['approvers'] as $approver) {
				if ($approver['type'] === 'circle') {
					if ($this->createShare($node, IShare::TYPE_CIRCLE, $approver['entityId'], $userId, $label)) {
						$createdShares[] = $approver;
					}
				}
			}
		}

		return $createdShares;
	}

	private function createShare(Node $node, int $type, string $sharedWith, string $sharedBy, string $label): bool {
		$share = $this->shareManager->newShare();
		$share->setNode($node)
			->setPermissions(Constants::PERMISSION_READ)
			->setSharedWith($sharedWith)
			->setShareType($type)
			->setSharedBy($sharedBy)
			->setExpirationDate(null);

		try {
			$share = $this->shareManager->createShare($share);
			$share->setLabel($label)
				->setNote($label);
			$share = $this->shareManager->updateShare($share);
			if ($type === IShare::TYPE_USER) {
				try {
					$this->shareManager->acceptShare($share, $sharedWith);
				} catch (GenericShareException | \Exception $e) {
				}
			}
			return true;
		} catch (GenericShareException | \Exception $e) {
			return false;
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

	public function propFind(PropFind $propFind, INode $node) {
		if (!$node instanceof SabreNode) {
			return;
		}
		$nodeId = $node->getId();
		// get state
		$state = $this->getApprovalState($nodeId, $this->userId);

		$propFind->handle(
			Application::DAV_PROPERTY_APPROVAL_STATE, function() use ($nodeId, $state) {
				error_log('HANDLE DAV_PROPERTY_APPROVAL_STATE for file '.$nodeId.' USER iS '.$this->userId);
				return $state['state'];
			}
		);
	}

	/**
	 * This is only called in Application::registerHooks()
	 * and is only usefull to propFind method
	 */
	public function setUserId(string $userId): void {
		$this->userId = $userId;
	}
}
