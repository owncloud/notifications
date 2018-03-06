<?php
/**
 * @author Tom Needham <tom@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH.
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
use OCA\Notifications\Tests\Unit\TestCase;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateTest extends TestCase {

	/** @var IURLGenerator | \PHPUnit_Framework_MockObject_MockObject */
	protected $urlGenerator;
	/** @var IManager | \PHPUnit_Framework_MockObject_MockObject */
	protected $manager;
	/** @var Generate */
	protected $command;
	/** @var CommandTester */
	protected $tester;
	/** @var IGroupManager | \PHPUnit_Framework_MockObject_MockObject */
	protected $groupManager;

	protected function setUp() {
		parent::setUp();

		$this->manager = $this->createMock(IManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->command = new Generate($this->manager, $this->urlGenerator, $this->groupManager);
		$this->tester = new CommandTester($this->command);
	}

	/**
	 * @expectedException \Exception
	 */
	public function testNoGroupOrUserGiven() {
		$options = [];
		$input = ['message' => 'test', 'subject' => 'test'];
		$response = $this->tester->execute($input, $options);
		$this->assertEquals(1, $response);
	}

	public function testSendNotificationForGroup() {
		$input = ['--group' => 'admin', 'message' => 'test', 'subject' => 'test'];
		$group = $this->createMock(IGroup::class);
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())->method('getUID')->willReturn('admin');
		$group->expects($this->once())->method('getUsers')->willReturn([$user]);
		$notification = $this->createMock(INotification::class);
		$this->manager->expects($this->once())->method('notify');
		$this->manager->expects($this->once())->method('createNotification')->willReturn($notification);
		$this->groupManager->expects($this->once())->method('get')->willReturn($group);
		$response = $this->tester->execute($input);
		$this->assertEquals(0, $response);
	}

	public function testSendNotificationForUser() {
		$input = ['--user' => 'admin', 'message' => 'test', 'subject' => 'test'];
		$notification = $this->createMock(INotification::class);
		$this->manager->expects($this->once())->method('notify');
		$this->manager->expects($this->once())->method('createNotification')->willReturn($notification);
		$response = $this->tester->execute($input);
		$this->assertEquals(0, $response);
	}


}
