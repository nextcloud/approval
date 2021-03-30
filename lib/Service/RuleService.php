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
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

use OCA\Approval\AppInfo\Application;

class RuleService {

	private $l10n;
	private $logger;

	/**
	 * Service to operate on tags
	 */
	public function __construct (string $appName,
								IConfig $config,
								LoggerInterface $logger,
								IDBConnection $db,
								IL10N $l10n) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->config = $config;
		$this->db = $db;
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
	 */
	private function hasConflict(?int $id, int $tagPending): bool {
		$qb = $this->db->getQueryBuilder();

		$or = $qb->expr()->orx();
		$or->add($qb->expr()->eq('tag_pending', $qb->createNamedParameter($tagPending, IQueryBuilder::PARAM_INT)));
		$or->add($qb->expr()->eq('tag_approved', $qb->createNamedParameter($tagPending, IQueryBuilder::PARAM_INT)));
		$or->add($qb->expr()->eq('tag_rejected', $qb->createNamedParameter($tagPending, IQueryBuilder::PARAM_INT)));
		$qb->andWhere($or);

		$qb->select('id')
			->from('approval_rules')
			->where($or);

		if (!is_null($id)) {
			$qb->andWhere(
				$qb->expr()->neq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		}
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			return true;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		return false;
	}

	/**
	 * Save the rule to DB if it has no conflict with others
	 *
	 * @param ?int $id rule id
	 * @param int $tagPending
	 * @param int $tagApproved
	 * @param int $tagRejected
	 * @param array $who
	 * @return null|string Error string
	 */
	public function saveRule(int $id, int $tagPending, int $tagApproved, int $tagRejected, array $who): array {
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
			$qb->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
			$req = $qb->execute();
			$qb = $qb->resetQueryParts();

		$rule = $this->getRule($id);

		// users
		$toDelete = [];
		$toAdd = [];
		$oldUserIds = [];
		foreach ($rule['who'] as $elem) {
			if (isset($elem['userId'])) {
				$oldUserIds[] = $elem['userId'];
			}
		}
		$newUserIds = [];
		foreach ($who as $elem) {
			if (isset($elem['userId'])) {
				$newUserIds[] = $elem['userId'];
			}
		}

		foreach ($oldUserIds as $uid) {
			if (!in_array($uid, $newUserIds)) {
				$toDelete[] = $uid;
			}
		}
		foreach ($newUserIds as $uid) {
			if (!in_array($uid, $oldUserIds)) {
				$toAdd[] = $uid;
			}
		}
		foreach ($toDelete as $uid) {
			$qb->delete('approval_rule_users')
				->where(
					$qb->expr()->eq('rule_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
                    $qb->expr()->eq('user_id', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR))
                );
			$req = $qb->execute();
			$qb = $qb->resetQueryParts();
		}
		foreach ($toAdd as $uid) {
			$qb->insert('approval_rule_users')
				->values([
					'user_id' => $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR),
					'rule_id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
				]);
			$req = $qb->execute();
			$qb = $qb->resetQueryParts();
		}

		// groups
		$toDelete = [];
		$toAdd = [];
		$oldGroupIds = [];
		foreach ($rule['who'] as $elem) {
			if (isset($elem['groupId'])) {
				$oldUserIds[] = $elem['groupId'];
			}
		}
		$newGroupIds = [];
		foreach ($who as $elem) {
			if (isset($elem['groupId'])) {
				$newGroupIds[] = $elem['groupId'];
			}
		}

		foreach ($oldGroupIds as $gid) {
			if (!in_array($gid, $newGroupIds)) {
				$toDelete[] = $gid;
			}
		}
		foreach ($newGroupIds as $gid) {
			if (!in_array($gid, $oldGroupIds)) {
				$toAdd[] = $gid;
			}
		}
		foreach ($toDelete as $gid) {
			$qb->delete('approval_rule_groups')
				->where(
					$qb->expr()->eq('rule_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
                    $qb->expr()->eq('group_id', $qb->createNamedParameter($gid, IQueryBuilder::PARAM_STR))
                );
			$req = $qb->execute();
			$qb = $qb->resetQueryParts();
		}
		foreach ($toAdd as $gid) {
			$qb->insert('approval_rule_groups')
				->values([
					'group_id' => $qb->createNamedParameter($gid, IQueryBuilder::PARAM_STR),
					'rule_id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
				]);
			$req = $qb->execute();
			$qb = $qb->resetQueryParts();
		}

		return ['id' => $id];
	}

	/**
	 * Save the rule to DB if it has no conflict with others
	 *
	 * @param int $tagPending
	 * @param int $tagApproved
	 * @param int $tagRejected
	 * @param array $who
	 * @return array id of created rule or error string
	 */
	public function createRule(int $tagPending, int $tagApproved, int $tagRejected, array $who): array {
		if (!$this->isValid($tagPending, $tagApproved, $tagRejected)) {
			return ['error' => 'Rule is invalid'];
		}
		if ($this->hasConflict(null, $tagPending)) {
			return ['error' => 'Rule conflicts'];
		}

		$qb = $this->db->getQueryBuilder();

		$qb->insert('approval_rules')
			->values([
				'tag_pending' => $qb->createNamedParameter($tagPending, IQueryBuilder::PARAM_INT),
				'tag_approved' => $qb->createNamedParameter($tagApproved, IQueryBuilder::PARAM_INT),
				'tag_rejected' => $qb->createNamedParameter($tagRejected, IQueryBuilder::PARAM_INT),
			]);
		$req = $qb->execute();
		$qb = $qb->resetQueryParts();

		$insertedRuleId = $qb->getLastInsertId();

		foreach ($who as $elem) {
			if (isset($elem['userId'])) {
				$qb->insert('approval_rule_users')
					->values([
						'user_id' => $qb->createNamedParameter($elem['userId'], IQueryBuilder::PARAM_STR),
						'rule_id' => $qb->createNamedParameter($insertedRuleId, IQueryBuilder::PARAM_INT),
					]);
				$req = $qb->execute();
				$qb = $qb->resetQueryParts();
			} elseif (isset($elem['groupId'])) {
				$qb->insert('approval_rule_groups')
					->values([
						'group_id' => $qb->createNamedParameter($elem['groupId'], IQueryBuilder::PARAM_STR),
						'rule_id' => $qb->createNamedParameter($insertedRuleId, IQueryBuilder::PARAM_INT),
					]);
				$req = $qb->execute();
				$qb = $qb->resetQueryParts();
			}
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
		if (is_null($this->getRule($id))) {
			return ['error' => 'Rule does not exist'];
		}

		$qb = $this->db->getQueryBuilder();

		$qb->delete('approval_rules')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
		$qb = $qb->resetQueryParts();

		$qb->delete('approval_rule_users')
			->where(
				$qb->expr()->eq('rule_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
		$qb = $qb->resetQueryParts();

		$qb->delete('approval_rule_groups')
			->where(
				$qb->expr()->eq('rule_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
		$qb = $qb->resetQueryParts();

		return [];
	}

	/**
	 * Get a rule by id
	 * @param int $id the rule id
	 * @return ?array the rule or null if not found
	 */
	public function getRule(int $id): ?array {
		$rule = null;
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('approval_rules')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			$tagPending = (int) $row['tag_pending'];
			$tagApproved = (int) $row['tag_approved'];
			$tagRejected = (int) $row['tag_rejected'];
			$rule = [
				'tagPending' => $tagPending,
				'tagApproved' => $tagApproved,
				'tagRejected' => $tagRejected,
				'who' => [],
			];
			break;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		if (is_null($rule)) {
			return $rule;
		}

		$qb->select('*')
			->from('approval_rule_users')
			->where(
				$qb->expr()->eq('rule_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			$rule['who'][] = [
				'userId' => $row['user_id']
			];
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		$qb->select('*')
			->from('approval_rule_groups')
			->where(
				$qb->expr()->eq('rule_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			$rule['who'][] = [
				'groupId' => $row['group_id']
			];
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		return $rule;
	}

	/**
	 * Get all rules
	 *
	 * @return array
	 */
	public function getRules(): array {
		$rules = [];
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('approval_rules');
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			$id = (int) $row['id'];
			$tagPending = (int) $row['tag_pending'];
			$tagApproved = (int) $row['tag_approved'];
			$tagRejected = (int) $row['tag_rejected'];
			$rules[$id] = [
				'tagPending' => $tagPending,
				'tagApproved' => $tagApproved,
				'tagRejected' => $tagRejected,
				'who' => [],
			];
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		foreach ($rules as $id => $rule) {
			$qb->select('*')
				->from('approval_rule_users')
				->where(
					$qb->expr()->eq('rule_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
				);
			$req = $qb->execute();
			while ($row = $req->fetch()) {
				$rules[$id]['who'][] = [
					'userId' => $row['user_id']
				];
			}
			$req->closeCursor();
			$qb = $qb->resetQueryParts();

			$qb->select('*')
				->from('approval_rule_groups')
				->where(
					$qb->expr()->eq('rule_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
				);
			$req = $qb->execute();
			while ($row = $req->fetch()) {
				$rules[$id]['who'][] = [
					'groupId' => $row['group_id']
				];
			}
			$req->closeCursor();
			$qb = $qb->resetQueryParts();
		}

		return $rules;
	}
}
