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
			->from('approval_setting')
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
	 * @param array $userIds
	 * @return null|string Error string
	 */
	public function saveRule(int $id, int $tagPending, int $tagApproved, int $tagRejected, array $userIds): array {
		if (!$this->isValid($tagPending, $tagApproved, $tagRejected)) {
			return ['error' => 'Invalid rule'];
		}
		if ($this->hasConflict($id, $tagPending)) {
			return ['error' => 'Rule conflict'];
		}

		$qb = $this->db->getQueryBuilder();

		$qb->update('approval_setting');
			$qb->set('tag_pending', $qb->createNamedParameter($tagPending, IQueryBuilder::PARAM_INT));
			$qb->set('tag_approved', $qb->createNamedParameter($tagApproved, IQueryBuilder::PARAM_INT));
			$qb->set('tag_rejected', $qb->createNamedParameter($tagRejected, IQueryBuilder::PARAM_INT));
			$qb->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
			$req = $qb->execute();
			$qb = $qb->resetQueryParts();

		$rule = $this->getRule($id);
		$toDelete = [];
		$toAdd = [];

		foreach ($rule['users'] as $uid) {
			if (!in_array($uid, $userIds)) {
				$toDelete[] = $uid;
			}
		}
		foreach ($userIds as $uid) {
			if (!in_array($uid, $rule['users'])) {
				$toAdd[] = $uid;
			}
		}
		foreach ($toDelete as $uid) {
			$qb->delete('approval_setting_user')
				->where(
					$qb->expr()->eq('setting_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
				)
				->andWhere(
                    $qb->expr()->eq('user_id', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR))
                );
			$req = $qb->execute();
			$qb = $qb->resetQueryParts();
		}
		foreach ($toAdd as $uid) {
			$qb->insert('approval_setting_user')
				->values([
					'user_id' => $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR),
					'setting_id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
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
	 * @param array $userIds
	 * @return array id of created rule or error string
	 */
	public function createRule(int $tagPending, int $tagApproved, int $tagRejected, array $userIds): array {
		if (!$this->isValid($tagPending, $tagApproved, $tagRejected)) {
			return ['error' => 'Rule is invalid'];
		}
		if ($this->hasConflict(null, $tagPending)) {
			return ['error' => 'Rule conflicts'];
		}

		$qb = $this->db->getQueryBuilder();

		$qb->insert('approval_setting')
			->values([
				'tag_pending' => $qb->createNamedParameter($tagPending, IQueryBuilder::PARAM_INT),
				'tag_approved' => $qb->createNamedParameter($tagApproved, IQueryBuilder::PARAM_INT),
				'tag_rejected' => $qb->createNamedParameter($tagRejected, IQueryBuilder::PARAM_INT),
			]);
		$req = $qb->execute();
		$qb = $qb->resetQueryParts();

		$insertedRuleId = $qb->getLastInsertId();

		foreach ($userIds as $userId) {
			$qb->insert('approval_setting_user')
				->values([
					'user_id' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
					'setting_id' => $qb->createNamedParameter($insertedRuleId, IQueryBuilder::PARAM_INT),
				]);
			$req = $qb->execute();
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
		if (is_null($this->getRule($id))) {
			return ['error' => 'Rule does not exist'];
		}

		$qb = $this->db->getQueryBuilder();

		$qb->delete('approval_setting')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
		$qb = $qb->resetQueryParts();

		$qb->delete('approval_setting_user')
			->where(
				$qb->expr()->eq('setting_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
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
			->from('approval_setting')
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
				'users' => [],
			];
			break;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		if (is_null($rule)) {
			return $rule;
		}

		$qb->select('*')
			->from('approval_setting_user')
			->where(
				$qb->expr()->eq('setting_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			$rule['users'][] = $row['user_id'];
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
			->from('approval_setting');
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
				'users' => [],
			];
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		foreach ($rules as $id => $rule) {
			$qb->select('*')
				->from('approval_setting_user')
				->where(
					$qb->expr()->eq('setting_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
				);
			$req = $qb->execute();
			while ($row = $req->fetch()) {
				$rules[$id]['users'][] = $row['user_id'];
			}
			$req->closeCursor();
			$qb = $qb->resetQueryParts();
		}

		return $rules;
	}
}
