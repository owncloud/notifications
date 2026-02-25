<?php
namespace OCA\notifications\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use OCP\Migration\ISchemaMigration;

/**
 * Change the type of the 'object_id' column from integer to string
 * Migration step required to update from ownCloud 8.2.x
 */
class Version20180604132522 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		$table = $schema->getTable("{$prefix}notifications");
		$column = $table->getColumn('object_id');
		if ($column->getType() === Type::getType(Types::INTEGER)) {
			$column->setType(Type::getType(Types::STRING));
		}
	}
}
