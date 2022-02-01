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

use DateTime;
use OCP\IL10N;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;
use OCP\IUserManager;
use OCP\IUser;
use OCP\IGroupManager;
use OCP\App\IAppManager;
use OCP\Notification\IManager as INotificationManager;

use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;

use OCA\DAV\Connector\Sabre\Node as SabreNode;
use Psr\Log\LoggerInterface;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;

use OCA\Approval\AppInfo\Application;
use OCA\Approval\Activity\ActivityManager;

class ApprovalService {
	private $tagObjectMapper;
	private $root;
	private $userManager;
	private $groupManager;
	private $appManager;
	private $notificationManager;
	private $ruleService;
	private $activityManager;
	private $utilsService;
	private $shareManager;
	private $l10n;
	private $userId;
	/**
	 * @var string
	 */
	private $appName;
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * ApprovalService constructor.
	 * @param string $appName
	 * @param ISystemTagObjectMapper $tagObjectMapper
	 * @param IRootFolder $root
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param IAppManager $appManager
	 * @param INotificationManager $notificationManager
	 * @param RuleService $ruleService
	 * @param ActivityManager $activityManager
	 * @param UtilsService $utilsService
	 * @param IShareManager $shareManager
	 * @param IL10N $l10n
	 * @param string|null $userId
	 */
	public function __construct(string $appName,
								ISystemTagObjectMapper $tagObjectMapper,
								IRootFolder $root,
								IUserManager $userManager,
								IGroupManager $groupManager,
								IAppManager $appManager,
								INotificationManager $notificationManager,
								RuleService $ruleService,
								ActivityManager $activityManager,
								UtilsService $utilsService,
								IShareManager $shareManager,
								IL10N $l10n,
								LoggerInterface $logger,
								?string $userId) {
		$this->tagObjectMapper = $tagObjectMapper;
		$this->root = $root;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->appManager = $appManager;
		$this->notificationManager = $notificationManager;
		$this->ruleService = $ruleService;
		$this->activityManager = $activityManager;
		$this->utilsService = $utilsService;
		$this->shareManager = $shareManager;
		$this->l10n = $l10n;
		$this->appName = $appName;
		$this->userId = $userId;
		$this->logger = $logger;
	}

	/**
	 * @param string $userId
	 * @param string $role
	 * @return array
	 */
	public function getBasicUserRules(string $userId, string $role): array {
		$userRules = [];
		$rules = $this->ruleService->getRules();

		foreach ($rules as $rule) {
			if ($this->userIsAuthorizedByRule($userId, $rule, $role)) {
				$userRules[] = $rule;
			}
		}
		return $userRules;
	}

	/**
	 * Get rules allowing user to request/approve
	 * If file ID is provided and role is requesters, avoid the rules for which the file is already pending/approved/rejected
	 *
	 * @param string $userId
	 * @param string $role
	 * @param int|null $fileId
	 * @return array
	 */
	public function getUserRules(string $userId, string $role = 'requesters', ?int $fileId = null): array {
		$userRules = [];
		$rules = $this->ruleService->getRules();

		$circlesEnabled = $this->appManager->isEnabledForUser('circles') && class_exists(\OCA\Circles\CirclesManager::class);
		$userNames = [];
		$circleNames = [];
		foreach ($rules as $rule) {
			if ($this->userIsAuthorizedByRule($userId, $rule, $role)) {
				// if looking for requester rules and we have a file ID:
				// avoid if it's already pending/approved/rejected for this rule
				if ($role === 'requesters'
					&& $fileId !== null
					&& ($this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagPending'])
						|| $this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagApproved'])
						|| $this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagRejected'])
					)
				) {
					continue;
				}
				$userRules[] = $rule;
				// get all entity ids
				foreach ($rule['approvers'] as $k => $elem) {
					if ($elem['type'] === 'user') {
						$userNames[$elem['entityId']] = null;
					} elseif ($elem['type'] === 'circle' && $circlesEnabled) {
						$circleNames[$elem['entityId']] = null;
					}
				}
				foreach ($rule['requesters'] as $k => $elem) {
					if ($elem['type'] === 'user') {
						$userNames[$elem['entityId']] = null;
					} elseif ($elem['type'] === 'circle' && $circlesEnabled) {
						$circleNames[$elem['entityId']] = null;
					}
				}
			}
		}
		// get display names
		foreach ($userNames as $k => $v) {
			$user = $this->userManager->get($k);
			$userNames[$k] = $user ? $user->getDisplayName() : $k;
		}
		if ($circlesEnabled) {
			$circlesManager = \OC::$server->get(\OCA\Circles\CirclesManager::class);
			$circlesManager->startSuperSession();
			foreach ($circleNames as $k => $v) {
				try {
					$circle = $circlesManager->getCircle($k);
					$circleNames[$k] = $circle->getDisplayName();
				} catch (\OCA\Circles\Exceptions\CircleNotFoundException $e) {
				}
			}
			$circlesManager->stopSession();
		}
		// affect names
		foreach ($userRules as $ruleIndex => $rule) {
			foreach ($rule['approvers'] as $approverIndex => $elem) {
				if ($elem['type'] === 'user') {
					$userRules[$ruleIndex]['approvers'][$approverIndex]['displayName'] = $userNames[$elem['entityId']];
				} elseif ($elem['type'] === 'group') {
					$userRules[$ruleIndex]['approvers'][$approverIndex]['displayName'] = $elem['entityId'];
				} elseif ($elem['type'] === 'circle' && $circlesEnabled) {
					$userRules[$ruleIndex]['approvers'][$approverIndex]['displayName'] = $circleNames[$elem['entityId']];
				}
			}
			foreach ($rule['requesters'] as $requesterIndex => $elem) {
				if ($elem['type'] === 'user') {
					$userRules[$ruleIndex]['requesters'][$requesterIndex]['displayName'] = $userNames[$elem['entityId']];
				} elseif ($elem['type'] === 'group') {
					$userRules[$ruleIndex]['requesters'][$requesterIndex]['displayName'] = $elem['entityId'];
				} elseif ($elem['type'] === 'circle' && $circlesEnabled) {
					$userRules[$ruleIndex]['requesters'][$requesterIndex]['displayName'] = $circleNames[$elem['entityId']];
				}
			}
		}
		return $userRules;
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
		$circlesEnabled = $this->appManager->isEnabledForUser('circles') && class_exists(\OCA\Circles\CirclesManager::class);

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
					if ($this->utilsService->isUserInCircle($userId, $circleId)) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * @param string $userId
	 * @param int|null $since
	 * @return array
	 */
	public function getPendingNodes(string $userId, ?int $since = null): array {
		$pendingNodes = [];
		// get pending tags i can approve
		$rules = $this->getBasicUserRules($userId, 'approvers');
		// search files with those tags which i have access to
		$userFolder = $this->root->getUserFolder($userId);
		foreach ($rules as $rule) {
			$pendingTagId = $rule['tagPending'];
			$ruleId = $rule['id'];
			$nodeIdsWithTag = $this->tagObjectMapper->getObjectIdsForTags($pendingTagId, 'files');
			// this actually does not work with tag IDs, only with tag names (not even sure it's about system tags...)
			// $nodes = $userFolder->searchByTag($pendingTagId, $userId);
			foreach ($nodeIdsWithTag as $nodeId) {
				// is the node in the user storage (does the user have access to this node)?
				$nodeInUserStorage = $userFolder->getById($nodeId);
				if (count($nodeInUserStorage) > 0 && !isset($pendingNodes[$nodeId])) {
					$node = $nodeInUserStorage[0];
					$pendingNodes[$nodeId] = [
						'node' => $node,
						'ruleId' => $ruleId,
					];
				}
			}
		}
		// get extra information
		$that = $this;
		$result =  array_map(function ($pendingNode) use ($that) {
			$node = $pendingNode['node'];
			$ruleId = $pendingNode['ruleId'];
			return [
				'file_id' => $node->getId(),
				'file_name' => $node->getName(),
				'mimetype' => $node->getMimetype(),
				'activity' => $that->ruleService->getLastAction($node->getId(), $ruleId, Application::STATE_PENDING),

			];
		}, array_values($pendingNodes));

		usort($result, function($a, $b) {
			if ($a['activity'] === null) {
				if ($b['activity'] === null) {
					return 0;
				} else {
					return 1;
				}
			} elseif ($b['activity'] === null) {
				return -1;
			}
            return ($a['activity']['timestamp'] > $b['activity']['timestamp']) ? -1 : 1;
        });

		return $result;
	}

	/**
	 * Get approval state of a given file for a given user
	 * @param int $fileId
	 * @param string|null $userId
	 * @return array state and rule id
	 */
	public function getApprovalState(int $fileId, ?string $userId): array {
		if (is_null($userId) || !$this->utilsService->userHasAccessTo($fileId, $userId)) {
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
	 * @param bool $createShares
	 * @return array potential error message
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function request(int $fileId, int $ruleId, ?string $userId, bool $createShares): array {
		$rule = $this->ruleService->getRule($ruleId);
		if (is_null($rule)) {
			return ['error' => $this->l10n->t('Rule does not exist')];
		}

		if ($this->userIsAuthorizedByRule($userId, $rule, 'requesters')) {
			// only request if it has not yet been requested for this rule
			if (!$this->tagObjectMapper->haveTag($fileId, 'files', $rule['tagPending'])) {
				if ($createShares) {
					$this->shareWithApprovers($fileId, $rule, $userId);
					// if shares are auto created, request is actually done in a separated request with $createShares === false
					return [];
				}
				// store activity in our tables
				$this->ruleService->storeAction($fileId, $ruleId, $userId, Application::STATE_PENDING);

				$this->tagObjectMapper->assignTags($fileId, 'files', $rule['tagPending']);

				// still produce an activity entry for the user who requests
				$this->activityManager->triggerEvent(
					ActivityManager::APPROVAL_OBJECT_NODE, $fileId,
					ActivityManager::SUBJECT_REQUESTED_ORIGIN,
					['origin_user_id' => $userId]
				);

				// check if someone can actually approve
				$ruleUserIds = $this->getRuleAuthorizedUserIds($rule, 'approvers');
				foreach ($ruleUserIds as $uid) {
					if ($this->utilsService->userHasAccessTo($fileId, $uid)) {
						return [];
					}
				}
				return ['warning' => $this->l10n->t('This element is not shared with any user who is authorized to approve it')];
			} else {
				return ['error' => $this->l10n->t('Approval has already been requested with this rule for this file')];
			}
		} else {
			return ['error' => $this->l10n->t('You are not authorized to request with this rule')];
		}
	}

	/**
	 * @param int $fileId
	 * @param int $ruleId
	 * @param string $requesterUserId
	 * @return array|string[]
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function requestViaTagAssignment(int $fileId, int $ruleId, string $requesterUserId): array {
		$rule = $this->ruleService->getRule($ruleId);
		if (is_null($rule)) {
			return ['error' => 'Rule does not exist'];
		}

		// WARNING we don't actually check if the requester is allowed to request with this rule here
		// because the request was not done with the UI but with manual/auto tag assignment => accept the request anyway
		$this->shareWithApprovers($fileId, $rule, $requesterUserId);
		// store activity in our tables
		$this->ruleService->storeAction($fileId, $ruleId, $requesterUserId, Application::STATE_PENDING);

		// still produce an activity entry for the user who requests
		$this->activityManager->triggerEvent(
			ActivityManager::APPROVAL_OBJECT_NODE, $fileId,
			ActivityManager::SUBJECT_REQUESTED_ORIGIN,
			['origin_user_id' => $requesterUserId]
		);

		// here we don't check if someone can actually approve, because there is nobody to warn
		return [];
	}

	/**
	 * Share file with everybody who can approve with given rule and have no access yet
	 *
	 * @param int $fileId
	 * @param array $rule
	 * @param string $userId
	 * @return array list of created shares
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	private function shareWithApprovers(int $fileId, array $rule, string $userId): array {
		$createdShares = [];
		// get node
		$userFolder = $this->root->getUserFolder($userId);
		$found = $userFolder->getById($fileId);
		if (count($found) > 0) {
			$node = $found[0];
		} else {
			return [];
		}
		$label = $this->l10n->t('Please check my approval request');

		foreach ($rule['approvers'] as $approver) {
			if ($approver['type'] === 'user' && !$this->utilsService->userHasAccessTo($fileId, $approver['entityId'])) {
				// create user share
				if ($this->utilsService->createShare($node, IShare::TYPE_USER, $approver['entityId'], $userId, $label)) {
					$createdShares[] = $approver;
				}
			}
		}
		if ($this->shareManager->allowGroupSharing()) {
			foreach ($rule['approvers'] as $approver) {
				if ($approver['type'] === 'group') {
					if ($this->utilsService->createShare($node, IShare::TYPE_GROUP, $approver['entityId'], $userId, $label)) {
						$createdShares[] = $approver;
					}
				}
			}
		}

		$circlesEnabled = $this->appManager->isEnabledForUser('circles') && class_exists(\OCA\Circles\CirclesManager::class);
		if ($circlesEnabled) {
			foreach ($rule['approvers'] as $approver) {
				if ($approver['type'] === 'circle') {
					if ($this->utilsService->createShare($node, IShare::TYPE_CIRCLE, $approver['entityId'], $userId, $label)) {
						$createdShares[] = $approver;
					}
				}
			}
		}

		return $createdShares;
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
				->setDateTime(new DateTime())
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
		$circlesEnabled = $this->appManager->isEnabledForUser('circles') && class_exists(\OCA\Circles\CirclesManager::class);
		if ($circlesEnabled) {
			$circlesManager = \OC::$server->get(\OCA\Circles\CirclesManager::class);
			$circlesManager->startSuperSession();
		}

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
				try {
					$circle = $circlesManager->getCircle($circleId);
					$circleMembers = $circle->getMembers();
					foreach ($circleMembers as $member) {
						// only consider users
						if ($member->getUserType() !== 1) {
							continue;
						}
						$memberUserId = $member->getUserId();
						if (!in_array($memberUserId, $ruleUserIds)) {
							$ruleUserIds[] = $memberUserId;
						}
					}
				} catch (\OCA\Circles\Exceptions\CircleNotFoundException $e) {
				}
			}
		}

		if ($circlesEnabled) {
			$circlesManager->stopSession();
		}
		return $ruleUserIds;
	}

	/**
	 * Called when a tag is assigned
	 *
	 * @param int $fileId
	 * @param array $tags
	 * @return void
	 */
	public function handleTagAssignmentEvent(int $fileId, array $tags): void {
		// which rule is involved?
		$ruleInvolded = null;
		$rules = $this->ruleService->getRules();
		foreach ($rules as $id => $rule) {
			// rule matches tags
			if (in_array($rule['tagPending'], $tags)) {
				$ruleInvolded = $rule;
				break;
			}
		}
		if (is_null($ruleInvolded)) {
			$this->logger->debug(
				'Could not request approval of file ' . $fileId . ': no rule found for tags ' . implode(',', $tags) . '.',
				['app' => Application::APP_ID]
			);
			return;
		}
		// search our activities to see if we know who made the request
		$activity = $this->ruleService->getLastAction($fileId, $ruleInvolded['id'], Application::STATE_PENDING);
		// if there is no activity, the tag was assigned manually (or via auto-tagging flows)
		// => perform the request here (share, store action and trigger activity event)
		if (is_null($activity)) {
			$found = $this->root->getById($fileId);
			if (count($found) > 0) {
				$node = $found[0];
			} else {
				$this->logger->error('Could not request approval of file ' . $fileId . ': file not found.', ['app' => Application::APP_ID]);
				return;
			}
			// get the requester user ID
			$requesterUserId = $this->userId;
			$requestResult = $this->requestViaTagAssignment($fileId, $ruleInvolded['id'], $requesterUserId);
			if (isset($requestResult['error'])) {
				$this->logger->error('Approval request error: ' . $requestResult['error'] . '.', ['app' => Application::APP_ID]);
				return;
			}
			$this->sendRequestNotification($fileId, $ruleInvolded, $requesterUserId, false);
		} else {
			// it was request via the approval interface, nothing more to do
			$requesterUserId = $activity['userId'];
			$this->sendRequestNotification($fileId, $ruleInvolded, $requesterUserId, true);
		}
	}

	/**
	 * Send notifications when a file approval is requested
	 * Send it to all users who are authorized to approve it
	 *
	 * @param int $fileId
	 * @param array $rule
	 * @param string|null $requestUserId
	 * @return void
	 */
	public function sendRequestNotification(int $fileId, array $rule, string $requestUserId, bool $checkAccess): void {
		// find users involved in rules matching tags
		$rulesUserIds = [];
		$thisRuleUserIds = $this->getRuleAuthorizedUserIds($rule, 'approvers');
		foreach ($thisRuleUserIds as $userId) {
			if (!in_array($userId, $rulesUserIds)) {
				$rulesUserIds[] = $userId;
			}
		}
		// create activity (which deals with access checks)
		$this->activityManager->triggerEvent(
			ActivityManager::APPROVAL_OBJECT_NODE, $fileId,
			ActivityManager::SUBJECT_MANUALLY_REQUESTED,
			['users' => $thisRuleUserIds, 'who' => $requestUserId]
		);

		$paramsByUser = [];
		$root = $this->root;
		if ($checkAccess) {
			// only notify users having access to the file
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
		} else {
			// we don't check if users have access to the file because they might not have yet (share is not effective yet)
			// => notify every approver
			foreach ($rulesUserIds as $userId) {
				$found = $root->getById($fileId);
				if (count($found) > 0) {
					$node = $found[0];
					// we don't know the path in user storage
					$path = '';
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
		}

		// actually send the notifications
		foreach ($paramsByUser as $userId => $params) {
			$manager = $this->notificationManager;
			$notification = $manager->createNotification();

			$subject = 'manual_request';
			$params['requesterId'] = $requestUserId;
			$notification->setApp(Application::APP_ID)
				->setUser($userId)
				->setDateTime(new DateTime())
				->setObject('dum', 'dum')
				->setSubject($subject, $params);

			$manager->notify($notification);
		}
	}

	/**
	 * Get approval state as a WebDav attribute
	 *
	 * @param PropFind $propFind
	 * @param INode $node
	 * @return void
	 */
	public function propFind(PropFind $propFind, INode $node): void {
		if (!$node instanceof SabreNode) {
			return;
		}
		$nodeId = $node->getId();
		// get state
		$state = $this->getApprovalState($nodeId, $this->userId);

		$propFind->handle(
			Application::DAV_PROPERTY_APPROVAL_STATE, function() use ($nodeId, $state) {
				return $state['state'];
			}
		);
	}
}
