<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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

namespace OCA\Notifications\Tests\Unit;

use OCA\Notifications\Handler;
use OCP\Notification\INotification;

/**
 * Class HandlerTest
 *
 * @group DB
 * @package OCA\Notifications\Tests\Lib
 */
class HandlerTest extends TestCase {
	/** @var \OCA\Notifications\Handler */
	protected $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->handler = new Handler(
			\OC::$server->getDatabaseConnection(),
			\OC::$server->getNotificationManager()
		);

		$this->handler->delete($this->getNotification([
			'getApp' => 'testing_notifications',
		]));
	}

	protected function tearDown(): void {
		parent::tearDown();
		$this->handler->delete($this->getNotification([
			'getApp' => 'testing_notifications',
		]));
	}

	public function testFull() {
		$notification = $this->getNotification([
			'getApp' => 'testing_notifications',
			'getUser' => 'test_user1',
			'getDateTime' => new \DateTime(),
			'getObjectType' => 'notification',
			'getObjectId' => '1337',
			'getSubject' => 'subject',
			'getSubjectParameters' => [],
			'getMessage' => 'message',
			'getMessageParameters' => [],
			'getLink' => 'link',
			'getActions' => [
				[
					'getLabel' => 'action_label',
					'getLink' => 'action_link',
					'getRequestType' => 'GET',
					'isPrimary' => false,
				]
			],
		]);
		$limitedNotification1 = $this->getNotification([
			'getApp' => 'testing_notifications',
			'getUser' => 'test_user1',
		]);
		$limitedNotification2 = $this->getNotification([
			'getApp' => 'testing_notifications',
			'getUser' => 'test_user2',
		]);

		// Make sure there is no notification
		$this->assertSame(0, $this->handler->count($limitedNotification1), 'Wrong notification count for user1 before adding');
		$notifications = $this->handler->get($limitedNotification1);
		$this->assertCount(0, $notifications, 'Wrong notification count for user1 before beginning');
		$this->assertSame(0, $this->handler->count($limitedNotification2), 'Wrong notification count for user2 before adding');
		$notifications = $this->handler->get($limitedNotification2);
		$this->assertCount(0, $notifications, 'Wrong notification count for user2 before beginning');

		// Add and count
		$this->handler->add($notification);
		$this->assertSame(1, $this->handler->count($limitedNotification1), 'Wrong notification count for user1 after adding');
		$this->assertSame(0, $this->handler->count($limitedNotification2), 'Wrong notification count for user2 after adding');

		// Get and count
		$notifications = $this->handler->get($limitedNotification1);
		$this->assertCount(1, $notifications, 'Wrong notification get for user1 after adding');
		$notifications = $this->handler->get($limitedNotification2);
		$this->assertCount(0, $notifications, 'Wrong notification get for user2 after adding');

		// Delete and count again
		$this->handler->delete($notification);
		$this->assertSame(0, $this->handler->count($limitedNotification1), 'Wrong notification count for user1 after deleting');
		$this->assertSame(0, $this->handler->count($limitedNotification2), 'Wrong notification count for user2 after deleting');
	}

	public function testDeleteUserNotifications() {
		$notification1 = $this->getNotification([
			'getApp' => 'testing_notifications',
			'getUser' => 'test_user1',
			'getDateTime' => new \DateTime(),
			'getObjectType' => 'notification',
			'getObjectId' => '1337',
			'getSubject' => 'subject',
			'getSubjectParameters' => [],
			'getMessage' => 'message',
			'getMessageParameters' => [],
			'getLink' => 'link',
			'getActions' => [
				[
					'getLabel' => 'action_label',
					'getLink' => 'action_link',
					'getRequestType' => 'GET',
					'isPrimary' => true,
				]
			],
		]);
		$notification2 = $this->getNotification([
			'getApp' => 'testing_notifications',
			'getUser' => 'test_user2',
			'getDateTime' => new \DateTime(),
			'getObjectType' => 'notification',
			'getObjectId' => '1337',
			'getSubject' => 'subject',
			'getSubjectParameters' => [],
			'getMessage' => 'message',
			'getMessageParameters' => [],
			'getLink' => 'link',
			'getActions' => [
				[
					'getLabel' => 'action_label',
					'getLink' => 'action_link',
					'getRequestType' => 'GET',
					'isPrimary' => true,
				]
			],
		]);
		$limitedNotification = $this->getNotification([
			'getApp' => 'testing_notifications',
			'getUser' => 'test_user1',
		]);
		$limitedNotification2 = $this->getNotification([
			'getApp' => 'testing_notifications',
			'getUser' => 'test_user2',
		]);

		$this->handler->add($notification1);
		$this->handler->add($notification2);
		$notifications = $this->handler->get($limitedNotification);
		$notificationId = \key($notifications);

		$this->handler->deleteUserNotifications($notification1->getUser());

		$this->assertNull($this->handler->getById($notificationId, 'test_user1'));

		$notifications2 = $this->handler->get($limitedNotification2);
		$notificationId2 = \key($notifications2);
		$result = $this->handler->getById($notificationId2, 'test_user2');
		$this->assertInstanceOf(INotification::class, $result);
		$this->assertEquals('test_user2', $result->getUser());
	}

	public function testDeleteById() {
		$notification = $this->getNotification([
			'getApp' => 'testing_notifications',
			'getUser' => 'test_user1',
			'getDateTime' => new \DateTime(),
			'getObjectType' => 'notification',
			'getObjectId' => '1337',
			'getSubject' => 'subject',
			'getSubjectParameters' => [],
			'getMessage' => 'message',
			'getMessageParameters' => [],
			'getLink' => 'link',
			'getActions' => [
				[
					'getLabel' => 'action_label',
					'getLink' => 'action_link',
					'getRequestType' => 'GET',
					'isPrimary' => true,
				]
			],
		]);
		$limitedNotification = $this->getNotification([
			'getApp' => 'testing_notifications',
			'getUser' => 'test_user1',
		]);

		// Make sure there is no notification
		$this->assertSame(0, $this->handler->count($limitedNotification));
		$notifications = $this->handler->get($limitedNotification);
		$this->assertCount(0, $notifications);

		// Add and count
		$this->handler->add($notification);
		$this->assertSame(1, $this->handler->count($limitedNotification));

		// Get and count
		$notifications = $this->handler->get($limitedNotification);
		$this->assertCount(1, $notifications);
		\reset($notifications);
		$notificationId = \key($notifications);

		// Get with wrong user
		$getNotification = $this->handler->getById($notificationId, 'test_user2');
		$this->assertSame(null, $getNotification);

		// Delete with wrong user
		$this->handler->deleteById($notificationId, 'test_user2');
		$this->assertSame(1, $this->handler->count($limitedNotification), 'Wrong notification count for user1 after trying to delete for user2');

		// Get with correct user
		$getNotification = $this->handler->getById($notificationId, 'test_user1');
		$this->assertInstanceOf('OCP\Notification\INotification', $getNotification);

		// Delete and count
		$this->handler->deleteById($notificationId, 'test_user1');
		$this->assertSame(0, $this->handler->count($limitedNotification), 'Wrong notification count for user1 after deleting');
	}

	/**
	 * @param array $values
	 * @return \OCP\Notification\INotification|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected function getNotification(array $values = []) {
		$notification = $this->getMockBuilder('OCP\Notification\INotification')
			->disableOriginalConstructor()
			->getMock();

		foreach ($values as $method => $returnValue) {
			if ($method === 'getActions') {
				$actions = [];
				foreach ($returnValue as $actionData) {
					$action = $this->getMockBuilder('OCP\Notification\IAction')
						->disableOriginalConstructor()
						->getMock();
					foreach ($actionData as $actionMethod => $actionValue) {
						$action->expects($this->any())
							->method($actionMethod)
							->willReturn($actionValue);
					}
					$actions[] = $action;
				}
				$notification->expects($this->any())
					->method($method)
					->willReturn($actions);
			} else {
				$notification->expects($this->any())
					->method($method)
					->willReturn($returnValue);
			}
		}

		$defaultDateTime = new \DateTime();
		$defaultDateTime->setTimestamp(0);
		$defaultValues = [
			'getApp' => '',
			'getUser' => '',
			'getDateTime' => $defaultDateTime,
			'getObjectType' => '',
			'getObjectId' => '',
			'getSubject' => '',
			'getSubjectParameters' => [],
			'getMessage' => '',
			'getMessageParameters' => [],
			'getLink' => '',
			'getActions' => [],
		];
		foreach ($defaultValues as $method => $returnValue) {
			if (isset($values[$method])) {
				continue;
			}

			$notification->expects($this->any())
				->method($method)
				->willReturn($returnValue);
		}

		$defaultValues = [
			'setApp',
			'setUser',
			'setDateTime',
			'setObject',
			'setSubject',
			'setMessage',
			'setLink',
			'addAction',
		];
		foreach ($defaultValues as $method) {
			$notification->expects($this->any())
				->method($method)
				->willReturnSelf();
		}

		return $notification;
	}

	public function testInsert() {
		$notification = $this->getNotification([
			'getApp' => 'testing_notifications',
			'getUser' => 'test_user1',
			'getDateTime' => new \DateTime(),
			'getObjectType' => 'notification',
			'getObjectId' => '1337',
			'getSubject' => 'subject',
		]);

		// Add and count
		$this->handler->add($notification);

		$limitedNotification = $this->getNotification([
			'getApp' => 'testing_notifications',
		]);

		$notifications = $this->handler->get($limitedNotification);
		$this->assertCount(1, $notifications);
	}
}
