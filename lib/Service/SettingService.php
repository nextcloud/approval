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

class SettingService {

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
		$this->tagManager = $tagManager;
		$this->tagObjectMapper = $tagObjectMapper;
	}

	/**
	 * @return void
	 */
	public function saveSetting(int $id, int $tagPending, int $tagApproved, int $tagRejected, array $userIds): void {
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

		$setting = $this->getSetting($id);
		$toDelete = [];
		$toAdd = [];

		foreach ($setting['users'] as $uid) {
			if (!in_array($uid, $userIds)) {
				$toDelete[] = $uid;
			}
		}
		foreach ($userIds as $uid) {
			if (!in_array($uid, $setting['users'])) {
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
	}

	/**
	 * @return int
	 */
	public function createSetting(int $tagPending, int $tagApproved, int $tagRejected, array $userIds): int {
		$qb = $this->db->getQueryBuilder();

		$qb->insert('approval_setting')
			->values([
				'tag_pending' => $qb->createNamedParameter($tagPending, IQueryBuilder::PARAM_INT),
				'tag_approved' => $qb->createNamedParameter($tagApproved, IQueryBuilder::PARAM_INT),
				'tag_rejected' => $qb->createNamedParameter($tagRejected, IQueryBuilder::PARAM_INT),
			]);
		$req = $qb->execute();
		$qb = $qb->resetQueryParts();

		$insertedSettingId = $qb->getLastInsertId();

		foreach ($userIds as $userId) {
			$qb->insert('approval_setting_user')
				->values([
					'user_id' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
					'setting_id' => $qb->createNamedParameter($insertedSettingId, IQueryBuilder::PARAM_INT),
				]);
			$req = $qb->execute();
			$qb = $qb->resetQueryParts();
		}

		return $insertedSettingId;
	}

	/**
	 * @return array
	 */
	public function deleteSetting(int $id): void {
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
	}

	/**
	 * @return array
	 */
	public function getSetting(int $id): ?array {
		$setting = null;
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
			$setting = [
				'tagPending' => $tagPending,
				'tagApproved' => $tagApproved,
				'tagRejected' => $tagRejected,
				'users' => [],
			];
			break;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		if (is_null($setting)) {
			return $setting;
		}

		$qb->select('*')
			->from('approval_setting_user')
			->where(
				$qb->expr()->eq('setting_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			$setting['users'][] = $row['user_id'];
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		return $setting;
	}

	/**
	 * @return array
	 */
	public function getSettings(): array {
		$settings = [];
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('approval_setting');
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			$id = (int) $row['id'];
			$tagPending = (int) $row['tag_pending'];
			$tagApproved = (int) $row['tag_approved'];
			$tagRejected = (int) $row['tag_rejected'];
			$settings[$id] = [
				'tagPending' => $tagPending,
				'tagApproved' => $tagApproved,
				'tagRejected' => $tagRejected,
				'users' => [],
			];
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		foreach ($settings as $id => $setting) {
			$qb->select('*')
				->from('approval_setting_user')
				->where(
					$qb->expr()->eq('setting_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
				);
			$req = $qb->execute();
			while ($row = $req->fetch()) {
				$settings[$id]['users'][] = $row['user_id'];
			}
			$req->closeCursor();
			$qb = $qb->resetQueryParts();
		}

		return $settings;
	}
}
