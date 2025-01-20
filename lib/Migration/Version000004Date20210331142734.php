<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Approval\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version000004Date20210331142734 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('approval_rules')) {
			$table = $schema->createTable('approval_rules');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('tag_pending', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('tag_approved', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('tag_rejected', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('approval_rule_requesters')) {
			$table = $schema->createTable('approval_rule_requesters');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('rule_id', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('entity_type', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('entity_id', Types::STRING, [
				'notnull' => true,
				'length' => 300,
			]);
			$table->setPrimaryKey(['id'], 'i');
		}

		if (!$schema->hasTable('approval_rule_approvers')) {
			$table = $schema->createTable('approval_rule_approvers');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('rule_id', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('entity_type', Types::INTEGER, [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('entity_id', Types::STRING, [
				'notnull' => true,
				'length' => 300,
			]);
			$table->setPrimaryKey(['id'], 'ii');
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
