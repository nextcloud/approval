<?php

declare(strict_types=1);

namespace OCA\Approval\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

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
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('tag_pending', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('tag_approved', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('tag_rejected', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('approval_rule_requesters')) {
			$table = $schema->createTable('approval_rule_requesters');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('rule_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('entity_type', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('entity_id', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->setPrimaryKey(['id'], 'i');
		}

		if (!$schema->hasTable('approval_rule_approvers')) {
			$table = $schema->createTable('approval_rule_approvers');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('rule_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('entity_type', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('entity_id', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->setPrimaryKey(['id'], 'i');
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
