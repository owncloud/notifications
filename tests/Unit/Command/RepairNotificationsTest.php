<?php
/**
 * @author Jannik Stehle <jstehle@owncloud.com>
 * @author Jan Ackermann <jackermann@owncloud.com>
 *
 * @copyright Copyright (c) 2021, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Notifications\Tests\Unit\Command;

use Doctrine\DBAL\Driver\Statement;
use OCA\Notifications\Command\Generate;
use OCA\Notifications\Command\RepairNotifications;
use OCA\Notifications\Tests\Unit\TestCase;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Console\Tester\CommandTester;

class RepairNotificationsTest extends TestCase {

	/** @var IDBConnection | \PHPUnit\Framework\MockObject\MockObject */
	protected $connection;
	/** @var Generate */
	protected $command;
	/** @var CommandTester */
	protected $tester;

	protected function setUp(): void {
		parent::setUp();

		$this->connection = $this->createMock(IDBConnection::class);
		$this->command = new RepairNotifications($this->connection);
		$this->tester = new CommandTester($this->command);
	}

	public function testInvalidSubject() {
		$this->expectException(\LogicException::class);

		$options = [];
		$input = ['subject' => 'test'];
		$this->tester->execute($input, $options);
	}

	public function testRepairLinks() {
		$options = [];
		$input = ['subject' => RepairNotifications::$availableSubjects[0]];

		$dbResult = [
			['notification_id' => 1, 'link' => 'http://owncloud.com/test', 'actions' => '[]']
		];

		$exprBuilder = $this->createMock(IExpressionBuilder::class);
		$statementMock = $this->createMock(Statement::class);
		$statementMock->method('fetchAll')->willReturn($dbResult);
		$qbMock = $this->createMock(IQueryBuilder::class);
		$qbMock->method('select')->willReturnSelf();
		$qbMock->method('from')->willReturnSelf();
		$qbMock->method('update')->willReturnSelf();
		$qbMock->method('where')->willReturnSelf();
		$qbMock->method('expr')->willReturn($exprBuilder);
		$qbMock->method('execute')->willReturn($statementMock);

		$this->connection->method('getQueryBuilder')->willReturn($qbMock);

		$response = $this->tester->execute($input, $options);
		$this->assertEquals(0, $response);
	}
}
