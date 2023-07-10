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
use OCA\Approval\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCP\IUserManager;

class RuleService {
	/**
	 * @var IDBConnection
	 */
	private $db;
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var IAppManager
	 */
	private $appManager;
	/**
	 * @var array
	 */
	private $strTypeToInt;
	/**
	 * @var string[]
	 */
	private $intTypeToStr;
	private ?array $cachedRules = null;

	/**
	 * Service to manage approval rules
	 */
	public function __construct(string $appName,
		IDBConnection $db,
		IUserManager $userManager,
		IAppManager $appManager) {
		$this->strTypeToInt = [
			'user' => Application::TYPE_USER,
			'group' => Application::TYPE_GROUP,
			'circle' => Application::TYPE_CIRCLE,
		];
		$this->intTypeToStr = [
			Application::TYPE_USER => 'user',
			Application::TYPE_GROUP => 'group',
			Application::TYPE_CIRCLE => 'circle',
		];
		$this->db = $db;
		$this->userManager = $userManager;
		$this->appManager = $appManager;
	}

	/**
	 * Check if a rule is valid
	 * All tags must be different
	 *
	 * @param int $tagPending
	 * @param int $tagApproved
	 * @param int $tagRejected
	 * @return bool true if rule is valid
	 */
	private function isValid(int $tagPending, int $tagApproved, int $tagRejected): bool {
		return $tagPending !== $tagApproved
			&& $tagPending !== $tagRejected
			&& $tagApproved !== $tagRejected;
	}

	/**
	 * Check if a rule is in conflict with others
	 * Check if this pending tag is found in another rule (as pending, approved or rejected)
	 *
	 * @param ?int $id rule id, null if not specified
	 * @param int $tagPending pending tag to search in other rules
	 * @return bool true if there is a conflict
	 * @throws \OCP\DB\Exception
	 */
	private function hasConflict(?int $id, int $tagPending): bool {
		$qb = $this->db->getQueryBuilder();

		$or = $qb->expr()->orx();
		$or->add($qb->expr()->eq('tag_pending', $qb->createNamedParameter($tagPending, IQueryBuilder::PARAM_INT)));
		// $or->add($qb->expr()->eq('tag_approved', $qb->createNamedParameter($tagPending, IQueryBuilder::PARAM_INT)));
		// $or->add($qb->expr()->eq('tag_rejected', $qb->createNamedParameter($tagPending, IQueryBuilder::PARAM_INT)));
		$qb->andWhere($or);

		$qb->select('id')
			->from('approval_rules')
			->where($or);

		if (!is_null($id)) {
			$qb->andWhere(
				$qb->expr()->neq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		}
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			return true;
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		return false;
	}

	/**
	 * Save the rule to DB if it has no conflict with others
	 *
	 * @param int $id rule id
	 * @param int $tagPending
	 * @param int $tagApproved
	 * @param int $tagRejected
	 * @param array $approvers
	 * @param array $requesters
	 * @param string $description
	 * @return array Error string or id of saved rule
	 * @throws \OCP\DB\Exception
	 */
	public function saveRule(int $id, int $tagPending, int $tagApproved, int $tagRejected,
		array $approvers, array $requesters, string $description): array {
		$this->cachedRules = null;
		if (!$this->isValid($tagPending, $tagApproved, $tagRejected)) {
			return ['error' => 'Invalid rule'];
		}
		if ($this->hasConflict($id, $tagPending)) {
			return ['error' => 'Rule conflict'];
		}

		$qb = $this->db->getQueryBuilder();

		$qb->update('approval_rules');
		$qb->set('tag_pending', $qb->createNamedParameter($tagPending, IQueryBuilder::PARAM_INT));
		$qb->set('tag_approved', $qb->createNamedParameter($tagApproved, IQueryBuilder::PARAM_INT));
		$qb->set('tag_rejected', $qb->createNamedParameter($tagRejected, IQueryBuilder::PARAM_INT));
		$qb->set('description', $qb->createNamedParameter($description, IQueryBuilder::PARAM_STR));
		$qb->where(
			$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
		);
		$qb->executeStatement();
		$qb = $qb->resetQueryParts();

		$rule = $this->getRule($id);

		$params = [
			'approvers' => $approvers,
			'requesters' => $requesters,
		];

		foreach ($params as $paramKey => $paramValue) {
			$toDelete = [];
			$toAdd = [];
			$oldIds = [
				'user' => [],
				'group' => [],
				'circle' => [],
			];
			foreach ($rule[$paramKey] as $elem) {
				$oldIds[$elem['type']][] = $elem['entityId'];
			}
			$newIds = [
				'user' => [],
				'group' => [],
				'circle' => [],
			];
			foreach ($paramValue as $elem) {
				$newIds[$elem['type']][] = $elem['entityId'];
			}

			foreach (['user', 'group', 'circle'] as $type) {
				foreach ($oldIds[$type] as $elemId) {
					if (!in_array($elemId, $newIds[$type])) {
						$toDelete[] = [
							'type' => $type,
							'entityId' => $elemId,
						];
					}
				}
				foreach ($newIds[$type] as $elemId) {
					if (!in_array($elemId, $oldIds[$type])) {
						$toAdd[] = [
							'type' => $type,
							'entityId' => $elemId,
						];
					}
				}
			}
			foreach ($toDelete as $td) {
				$qb->delete('approval_rule_' . $paramKey)
					->where(
						$qb->expr()->eq('rule_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
					)
					->andWhere(
						$qb->expr()->eq('entity_type', $qb->createNamedParameter($this->strTypeToInt[$td['type']], IQueryBuilder::PARAM_INT))
					)
					->andWhere(
						$qb->expr()->eq('entity_id', $qb->createNamedParameter($td['entityId'], IQueryBuilder::PARAM_STR))
					);
				$qb->executeStatement();
				$qb = $qb->resetQueryParts();
			}
			foreach ($toAdd as $ta) {
				$qb->insert('approval_rule_' . $paramKey)
					->values([
						'entity_type' => $qb->createNamedParameter($this->strTypeToInt[$ta['type']], IQueryBuilder::PARAM_INT),
						'entity_id' => $qb->createNamedParameter($ta['entityId'], IQueryBuilder::PARAM_STR),
						'rule_id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
					]);
				$qb->executeStatement();
				$qb = $qb->resetQueryParts();
			}
		}

		return ['id' => $id];
	}

	/**
	 * Save the rule to DB if it has no conflict with others
	 *
	 * @param int $tagPending
	 * @param int $tagApproved
	 * @param int $tagRejected
	 * @param array $approvers
	 * @param array $requesters
	 * @param string $description
	 * @return array id of created rule or error string
	 */
	public function createRule(int $tagPending, int $tagApproved, int $tagRejected,
		array $approvers, array $requesters, string $description): array {
		$this->cachedRules = null;
		if (!$this->isValid($tagPending, $tagApproved, $tagRejected)) {
			return ['error' => 'Rule is invalid'];
		}
		if ($this->hasConflict(null, $tagPending)) {
			return ['error' => 'Rule conflict'];
		}

		$qb = $this->db->getQueryBuilder();

		$qb->insert('approval_rules')
			->values([
				'tag_pending' => $qb->createNamedParameter($tagPending, IQueryBuilder::PARAM_INT),
				'tag_approved' => $qb->createNamedParameter($tagApproved, IQueryBuilder::PARAM_INT),
				'tag_rejected' => $qb->createNamedParameter($tagRejected, IQueryBuilder::PARAM_INT),
				'description' => $qb->createNamedParameter($description, IQueryBuilder::PARAM_STR),
			]);
		$qb->executeStatement();
		$qb = $qb->resetQueryParts();

		$insertedRuleId = $qb->getLastInsertId();

		foreach ($approvers as $elem) {
			$qb->insert('approval_rule_approvers')
				->values([
					'entity_id' => $qb->createNamedParameter($elem['entityId'], IQueryBuilder::PARAM_STR),
					'entity_type' => $qb->createNamedParameter($this->strTypeToInt[$elem['type']], IQueryBuilder::PARAM_INT),
					'rule_id' => $qb->createNamedParameter($insertedRuleId, IQueryBuilder::PARAM_INT),
				]);
			$qb->executeStatement();
			$qb = $qb->resetQueryParts();
		}
		foreach ($requesters as $elem) {
			$qb->insert('approval_rule_requesters')
				->values([
					'entity_id' => $qb->createNamedParameter($elem['entityId'], IQueryBuilder::PARAM_STR),
					'entity_type' => $qb->createNamedParameter($this->strTypeToInt[$elem['type']], IQueryBuilder::PARAM_INT),
					'rule_id' => $qb->createNamedParameter($insertedRuleId, IQueryBuilder::PARAM_INT),
				]);
			$qb->executeStatement();
			$qb = $qb->resetQueryParts();
		}

		return ['id' => $insertedRuleId];
	}

	/**
	 * Delete a rule
	 *
	 * @param int $id the rule id
	 * @return array potential error
	 */
	public function deleteRule(int $id): array {
		$this->cachedRules = null;
		if (is_null($this->getRule($id))) {
			return ['error' => 'Rule does not exist'];
		}

		$qb = $this->db->getQueryBuilder();

		$qb->delete('approval_rules')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
		$qb = $qb->resetQueryParts();

		$qb->delete('approval_rule_approvers')
			->where(
				$qb->expr()->eq('rule_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
		$qb = $qb->resetQueryParts();

		$qb->delete('approval_rule_requesters')
			->where(
				$qb->expr()->eq('rule_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
		$qb = $qb->resetQueryParts();

		$qb->delete('approval_activity')
			->where(
				$qb->expr()->eq('rule_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
		$qb->resetQueryParts();

		return [];
	}

	/**
	 * Get a rule by id
	 * @param int $id the rule id
	 * @return array|null the rule or null if not found
	 */
	public function getRule(int $id): ?array {
		$rule = null;
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('approval_rules')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$tagPending = (int) $row['tag_pending'];
			$tagApproved = (int) $row['tag_approved'];
			$tagRejected = (int) $row['tag_rejected'];
			$description = $row['description'];
			$rule = [
				'id' => $id,
				'tagPending' => $tagPending,
				'tagApproved' => $tagApproved,
				'tagRejected' => $tagRejected,
				'description' => $description,
				'approvers' => [],
				'requesters' => [],
			];
			break;
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		if (is_null($rule)) {
			return null;
		}

		$rule['approvers'] = $this->getRuleEntities($id, 'approvers');
		$rule['requesters'] = $this->getRuleEntities($id, 'requesters');

		return $rule;
	}

	/**
	 * Get all rules
	 *
	 * @return array
	 */
	public function getRules(): array {
		if ($this->cachedRules === null) {
			$rules = [];
			$qb = $this->db->getQueryBuilder();

			$qb->select('*')
				->from('approval_rules');
			$req = $qb->executeQuery();
			while ($row = $req->fetch()) {
				$id = (int)$row['id'];
				$tagPending = (int)$row['tag_pending'];
				$tagApproved = (int)$row['tag_approved'];
				$tagRejected = (int)$row['tag_rejected'];
				$description = $row['description'];
				$rules[$id] = [
					'id' => $id,
					'tagPending' => $tagPending,
					'tagApproved' => $tagApproved,
					'tagRejected' => $tagRejected,
					'description' => $description,
					'approvers' => [],
					'requesters' => [],
				];
			}
			$req->closeCursor();
			$qb->resetQueryParts();

			foreach ($rules as $id => $rule) {
				$rules[$id]['approvers'] = $this->getRuleEntities($id, 'approvers');
				$rules[$id]['requesters'] = $this->getRuleEntities($id, 'requesters');
			}
			$this->cachedRules = $rules;
		}

		return $this->cachedRules;
	}

	/**
	 * Get entities associated to a rule (approvers or requesters)
	 *
	 * @param int $ruleId
	 * @param string $role 'approvers' or 'requesters'
	 * @return array
	 */
	private function getRuleEntities(int $ruleId, string $role): array {
		$circlesEnabled = $this->appManager->isEnabledForUser('circles');
		$entities = [];

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('approval_rule_' . $role)
			->where(
				$qb->expr()->eq('rule_id', $qb->createNamedParameter($ruleId, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->executeQuery();
		while ($row = $req->fetch()) {
			$type = (int)$row['entity_type'];
			if ($type !== Application::TYPE_CIRCLE || $circlesEnabled) {
				$entities[] = [
					'entityId' => $row['entity_id'],
					'type' => $this->intTypeToStr[$type],
				];
			}
		}
		$req->closeCursor();
		$qb->resetQueryParts();
		return $entities;
	}

	/**
	 * Store approval action
	 *
	 * @param int $fileId
	 * @param int $ruleId
	 * @param string $userId
	 * @param int $newState
	 * @return void
	 */
	public function storeAction(int $fileId, int $ruleId, string $userId, int $newState): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('approval_activity')
			->where(
				$qb->expr()->eq('rule_id', $qb->createNamedParameter($ruleId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
		$qb = $qb->resetQueryParts();

		$timestamp = (new DateTime())->getTimestamp();
		$qb->insert('approval_activity')
			->values([
				'file_id' => $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT),
				'rule_id' => $qb->createNamedParameter($ruleId, IQueryBuilder::PARAM_INT),
				'user_id' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
				'new_state' => $qb->createNamedParameter($newState, IQueryBuilder::PARAM_INT),
				'timestamp' => $qb->createNamedParameter($timestamp, IQueryBuilder::PARAM_INT),
			]);
		$qb->executeStatement();
		$qb->resetQueryParts();
	}

	/**
	 * Get last action that brought a file to a given state
	 *
	 * @param int $fileId
	 * @param int $ruleId
	 * @param int $newState
	 * @return array|null
	 */
	public function getLastAction(int $fileId, int $ruleId, int $newState): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('approval_activity')
			->where(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('rule_id', $qb->createNamedParameter($ruleId, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('new_state', $qb->createNamedParameter($newState, IQueryBuilder::PARAM_INT))
			);
		$qb->orderBy('timestamp', 'DESC');
		$qb->setMaxResults(1);
		$req = $qb->executeQuery();
		$activity = null;
		while ($row = $req->fetch()) {
			$activity = [
				'userId' => $row['user_id'],
				'timestamp' => (int) $row['timestamp'],
			];
			break;
		}
		$req->closeCursor();
		$qb->resetQueryParts();

		if (!is_null($activity)) {
			// get user display name
			$user = $this->userManager->get($activity['userId']);
			$activity['userName'] = $user ? $user->getDisplayName() : $activity['userId'];
		}
		return $activity;
	}
}
