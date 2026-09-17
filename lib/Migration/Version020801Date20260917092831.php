<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Migration;

use Closure;
use OCA\Approval\AppInfo\Application;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020801Date20260917092831 extends SimpleMigrationStep {

	public function __construct(
		private IDBConnection $connection,
		private IUserManager $userManager,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		foreach (['approvers', 'requesters'] as $role) {
			$qb = $this->connection->getQueryBuilder();
			$qb->selectDistinct('entity_id')
				->from('approval_rule_' . $role)
				->where(
					$qb->expr()->eq('entity_type', $qb->createNamedParameter(Application::TYPE_USER, IQueryBuilder::PARAM_INT))
				);

			$result = $qb->executeQuery();
			$userIds = $result->fetchAll(\PDO::FETCH_COLUMN);
			$result->closeCursor();

			foreach ($userIds as $userId) {
				if ($this->userManager->get($userId) === null) {
					$deleteQb = $this->connection->getQueryBuilder();
					$deleteQb->delete('approval_rule_' . $role)
						->where(
							$deleteQb->expr()->eq('entity_type', $deleteQb->createNamedParameter(Application::TYPE_USER, IQueryBuilder::PARAM_INT))
						)
						->andWhere(
							$deleteQb->expr()->eq('entity_id', $deleteQb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
						);
					$deleteQb->executeStatement();
				}
			}
		}
	}
}
