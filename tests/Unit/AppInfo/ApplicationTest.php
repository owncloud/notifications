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

namespace OCA\Notifications\Tests\Unit\AppInfo;

use OC\AppFramework\DependencyInjection\DIContainer;
use OCA\Notifications\AppInfo\Application;
use OCA\Notifications\Handler;
use OCA\Notifications\Tests\Unit\TestCase;
use Test\Traits\UserTrait;

/**
 * Class ApplicationTest
 *
 * @group DB
 * @package OCA\Notifications\Tests\AppInfo
 */
class ApplicationTest extends TestCase {
	use UserTrait;
	/** @var \OCA\Notifications\AppInfo\Application */
	protected $app;

	/** @var \OCP\AppFramework\IAppContainer */
	protected $container;

	protected function setUp() {
		parent::setUp();
		$this->app = new Application();
		$this->app->setupSymfonyEventListeners();
		$this->container = $this->app->getContainer();
	}

	public function testContainerAppName() {
		$this->app = new Application();
		$this->assertEquals('notifications', $this->container->getAppName());
	}

	public function dataContainerQuery() {
		return [
			['EndpointController', 'OCA\Notifications\Controller\EndpointController'],
			['Capabilities', 'OCA\Notifications\Capabilities'],
		];
	}

	/**
	 * @dataProvider dataContainerQuery
	 * @param string $service
	 * @param string $expected
	 */
	public function testContainerQuery($service, $expected) {
		$this->assertTrue($this->container->query($service) instanceof $expected);
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

	public function testSetupSymfonyEventListeners() {
		$user1 = $this->createUser('user1');

		$notification1 = $this->getNotification([
			'getApp' => 'testing_notifications',
			'getUser' => $user1->getUID(),
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

		$limitedNotification1 = $this->getNotification([
			'getApp' => 'testing_notifications',
			'getUser' => $user1->getUID(),
		]);

		$handler = $this->container->query(Handler::class);
		$handler->add($notification1);

		//Now delete user1 to so that symofny event for user.afterdelete could be listened and
		//the data from the notifications can be deleted.
		$useriUID = $user1->getUID();
		$this->assertTrue($user1->delete());

		$notifications1 = $handler->get($limitedNotification1);
		$notificationId1 = \key($notifications1);

		$this->assertNull($handler->getById($notificationId1, $useriUID));
	}
}
