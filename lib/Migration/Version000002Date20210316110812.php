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
class Version000002Date20210316110812 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('approval_setting')) {
			$table = $schema->createTable('approval_setting');
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

		if (!$schema->hasTable('approval_setting_user')) {
			$table = $schema->createTable('approval_setting_user');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('setting_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 300,
			]);
			$table->setPrimaryKey(['id']);
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
