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

use OCA\Notifications\Command\Generate;
use OCA\Notifications\Command\RepairNotifications;
use OCA\Notifications\Handler;
use OCA\Notifications\Tests\Unit\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class RepairNotificationsTest extends TestCase {

	/** @var Handler | \PHPUnit\Framework\MockObject\MockObject */
	protected $handler;
	/** @var Generate */
	protected $command;
	/** @var CommandTester */
	protected $tester;

	protected function setUp(): void {
		parent::setUp();

		$this->handler = $this->createMock(Handler::class);
		$this->command = new RepairNotifications($this->handler);
		$this->tester = new CommandTester($this->command);
	}

	public function testInvalidSubject() {
		$options = [];
		$input = ['subject' => 'test'];
		$response = $this->tester->execute($input, $options);
		$this->assertEquals(1, $response);
	}

	public function testRepairLinks() {
		$options = [];
		$input = ['subject' => RepairNotifications::$availableSubjects[0]];

		$this->handler->expects($this->once())->method('removeBaseUrlFromAbsoluteLinks');

		$response = $this->tester->execute($input, $options);
		$this->assertEquals(0, $response);
	}
}
