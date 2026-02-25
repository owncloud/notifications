<?php

namespace OCA\notifications\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use OCP\Migration\ISchemaMigration;

class Version20170801085340 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		if ($schema->hasTable("{$prefix}notifications")) {
			return;
		}

		$table = $schema->createTable("{$prefix}notifications");
		$table->addColumn('notification_id', Types::INTEGER, ['autoincrement' => true]);
		$table->addColumn('app', Types::STRING, ['length' => 32]);
		$table->addColumn('user', Types::STRING, ['length' => 64]);
		$table->addColumn('timestamp', Types::INTEGER, ['default' => 0]);
		$table->addColumn('object_type', Types::STRING, ['length' => 64]);
		$table->addColumn('object_id', Types::STRING, ['length' => 64]);
		$table->addColumn('subject', Types::STRING, ['length' => 64]);
		$table->addColumn('subject_parameters', Types::TEXT, ['notNull' => false]);
		$table->addColumn('message', Types::STRING, ['length' => 64]);
		$table->addColumn('message_parameters', Types::TEXT, ['notNull' => false]);
		$table->addColumn('link', Types::STRING, ['length' => 4000]);
		$table->addColumn('actions', Types::TEXT, ['notNull' => false]);
		$table->addColumn('icon', Types::STRING, ['length' => 4000, 'notNull' => false]);

		$table->setPrimaryKey(['notification_id']);
		$table->addIndex(['app']);
		$table->addIndex(['user']);
		$table->addIndex(['timestamp']);
		$table->addIndex(['object_type', 'object_id']);
	}
}
