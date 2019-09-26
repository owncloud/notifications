<?php
/**
 * @author Juan Pablo Villafáñez <jvillafanez@solidgear.es>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
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

namespace OCA\Notifications\Tests\Unit\Controller;

use OCP\AppFramework\Http;
use OCP\IUser;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Notification\IAction;
use OCA\Notifications\Handler;
use OCA\Notifications\Controller\EndpointV2Controller;

class EndpointV2ControllerTest extends \Test\TestCase {
	/** @var Handler */
	private $handler;

	/** @var IManager */
	private $manager;

	/** @var IUserSession */
	private $userSession;

	/** @var IConfig */
	private $config;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var EndpointV2Controller */
	private $controller;

	protected function setUp() {
		parent::setUp();

		$this->request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()
			->getMock();
		$this->handler = $this->getMockBuilder(Handler::class)
			->disableOriginalConstructor()
			->getMock();
		$this->manager = $this->getMockBuilder(IManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession = $this->getMockBuilder(IUserSession::class)
			->disableOriginalConstructor()
			->getMock();
		$this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$this->urlGenerator = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()
			->getMock();

		$this->controller = new EndpointV2Controller($this->handler, $this->manager, $this->userSession, $this->config, $this->urlGenerator, $this->request);
	}

	private function getNotificationList() {
		$result = [];
		$action = $this->getMockBuilder(IAction::class)
			->disableOriginalConstructor()
			->getMock();
		$action->method('getParsedLabel')
			->willReturn('qwerty');
		$action->method('getLink')
			->willReturn('link');
		$action->method('getRequestType')
			->willReturn('HEAD');
		$action->method('isPrimary')
			->willReturn(false);
		for ($i = 5; $i <= 40; $i++) {
			$notification = $this->getMockBuilder(INotification::class)
				->disableOriginalConstructor()
				->getMock();
			$notification->method('getObjectType')
				->willReturn('test_notification');
			$notification->method('getObjectId')
				->willReturn(strval($i));
			$notification->method('getDateTime')
				->willReturn(new \DateTime());

			if ($i > 30) {
				$notification->method('getParsedActions')
					->willReturn([$action]);
			} else {
				$notification->method('getParsedActions')
					->willReturn([]);
			}
			$result[$i] = $notification;
		}
		return $result;
	}

	private function isKeySortedBottomToTop(array $arr) {
		$previousId = null;
		foreach ($arr as $key => $value) {
			if ($previousId !== null) {
				if ($previousId > $value['notification_id']) {
					return false;
				}
			}
			$previousId = $value['notification_id'];
		}
		return true;
	}

	private function isKeySortedTopToBottom(array $arr) {
		$previousId = null;
		foreach ($arr as $key => $value) {
			if ($previousId !== null) {
				if ($previousId < $value['notification_id']) {
					return false;
				}
			}
			$previousId = $value['notification_id'];
		}
		return true;
	}

	public function testListNotificationsWrongUser() {
		$this->userSession->method('getUser')
			->willReturn(null);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $this->controller->listNotifications()->getStatus());
	}

	/**
	 * the id is forwarded directly to the handler, so no need to test with different ids
	 */
	public function testListNotificationsDescendent() {
		$maxResults = EndpointV2Controller::ENFORCED_LIST_LIMIT;
		$notificationList = $this->getNotificationList();
		krsort($notificationList);
		$notificationList = array_slice($notificationList, 0, $maxResults + 1, true);

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->method('getUID')
			->willReturn('test_user1');
		$this->userSession->method('getUser')
			->willReturn($user);

		$this->handler->method('getMaxNotificationId')
			->willReturn(123);  // purposely use a different max id than the one returned in the list
		$this->handler->method('fetchDescendentList')
			->with('test_user1', null, $maxResults + 1, $this->anything())
			->willReturn($notificationList);

		$this->manager->method('prepare')
			->will($this->returnArgument(0));

		$this->urlGenerator->method('linkToRoute')
			->willReturn('http://server/owncloud/route?id=20&fetch=desc&limit=20');
		// we won't check the url returned by the urlGenerator, any path will do

		$ocsResult = $this->controller->listNotifications();
		$this->assertEquals(Http::STATUS_OK, $ocsResult->getStatus());

		$rawData = json_decode($ocsResult->render(), true);
		$rawData = $rawData['ocs']['data'];
		$this->assertEquals($maxResults, count($rawData['notifications']));
		$this->assertTrue($this->isKeySortedTopToBottom($rawData['notifications']));
		$this->assertArrayHasKey('next', $rawData);
		$this->assertEquals('http://server/owncloud/route?id=20&fetch=desc&limit=20', $rawData['next']);

		$resultHeaders = $ocsResult->getHeaders();
		$this->assertArrayHasKey('OC-Last-Notification', $resultHeaders);
		$this->assertEquals(123, $resultHeaders['OC-Last-Notification']);
	}

	public function testListNotificationsDescendentNoNotifications() {
		$maxResults = EndpointV2Controller::ENFORCED_LIST_LIMIT;
		$notificationList = $this->getNotificationList();
		krsort($notificationList);
		$notificationList = array_slice($notificationList, 0, $maxResults + 1, true);

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->method('getUID')
			->willReturn('test_user1');
		$this->userSession->method('getUser')
			->willReturn($user);

		$this->handler->method('getMaxNotificationId')
			->willReturn(null);  // purposely use a different max id than the one returned in the list
		$this->handler->method('fetchDescendentList')
			->with('test_user1', null, $maxResults + 1, $this->anything())
			->willReturn([]);

		$this->manager->method('prepare')
			->will($this->returnArgument(0));

		$this->urlGenerator->method('linkToRoute')
			->willReturn('http://server/owncloud/route?id=20&fetch=desc&limit=20');
		// we won't check the url returned by the urlGenerator, any path will do

		$ocsResult = $this->controller->listNotifications();
		$this->assertEquals(Http::STATUS_OK, $ocsResult->getStatus());

		$rawData = json_decode($ocsResult->render(), true);
		$rawData = $rawData['ocs']['data'];
		$this->assertEquals(0, count($rawData['notifications']));
		$this->assertTrue($this->isKeySortedTopToBottom($rawData['notifications']));
		$this->assertArrayNotHasKey('next', $rawData);

		$resultHeaders = $ocsResult->getHeaders();
		$this->assertArrayHasKey('OC-Last-Notification', $resultHeaders);
		$this->assertEquals(-1, $resultHeaders['OC-Last-Notification']);  // header should return -1 instead of null
	}

	/**
	 * the id is forwarded directly to the handler, so need to test with different ids
	 */
	public function testListNotificationsAscendent() {
		$maxResults = EndpointV2Controller::ENFORCED_LIST_LIMIT;
		$notificationList = $this->getNotificationList();
		$notificationList = array_slice($notificationList, 0, $maxResults + 1, true);

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->method('getUID')
			->willReturn('test_user1');
		$this->userSession->method('getUser')
			->willReturn($user);

		$this->handler->method('getMaxNotificationId')
			->willReturn(123);  // purposely use a different max id than the one returned in the list
		$this->handler->method('fetchAscendentList')
			->with('test_user1', null, $maxResults + 1, $this->anything())
			->willReturn($notificationList);

		$this->manager->method('prepare')
			->will($this->returnArgument(0));

		$this->urlGenerator->method('linkToRoute')
			->willReturn('http://server/owncloud/route?id=20&fetch=asc&limit=20');
		// we won't check the url returned by the urlGenerator, any path will do

		$ocsResult = $this->controller->listNotifications(null, 'asc');
		$this->assertEquals(Http::STATUS_OK, $ocsResult->getStatus());

		$rawData = json_decode($ocsResult->render(), true);
		$rawData = $rawData['ocs']['data'];
		$this->assertEquals($maxResults, count($rawData['notifications']));
		$this->assertTrue($this->isKeySortedBottomToTop($rawData['notifications']));
		$this->assertArrayHasKey('next', $rawData);
		$this->assertEquals('http://server/owncloud/route?id=20&fetch=asc&limit=20', $rawData['next']);

		$resultHeaders = $ocsResult->getHeaders();
		$this->assertArrayHasKey('OC-Last-Notification', $resultHeaders);
		$this->assertEquals(123, $resultHeaders['OC-Last-Notification']);
	}

	public function testListNotificationsAscendentNoNotifications() {
		$maxResults = EndpointV2Controller::ENFORCED_LIST_LIMIT;
		$notificationList = $this->getNotificationList();
		$notificationList = array_slice($notificationList, 0, $maxResults + 1, true);

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->method('getUID')
			->willReturn('test_user1');
		$this->userSession->method('getUser')
			->willReturn($user);

		$this->handler->method('getMaxNotificationId')
			->willReturn(null);
		$this->handler->method('fetchAscendentList')
			->with('test_user1', null, $maxResults + 1, $this->anything())
			->willReturn([]);

		$this->manager->method('prepare')
			->will($this->returnArgument(0));

		$this->urlGenerator->method('linkToRoute')
			->willReturn('http://server/owncloud/route?id=20&fetch=asc&limit=20');
		// we won't check the url returned by the urlGenerator, any path will do

		$ocsResult = $this->controller->listNotifications(null, 'asc');
		$this->assertEquals(Http::STATUS_OK, $ocsResult->getStatus());

		$rawData = json_decode($ocsResult->render(), true);
		$rawData = $rawData['ocs']['data'];
		$this->assertEquals(0, count($rawData['notifications']));
		$this->assertTrue($this->isKeySortedBottomToTop($rawData['notifications']));
		$this->assertArrayNotHasKey('next', $rawData);

		$resultHeaders = $ocsResult->getHeaders();
		$this->assertArrayHasKey('OC-Last-Notification', $resultHeaders);
		$this->assertEquals(-1, $resultHeaders['OC-Last-Notification']);
	}

	public function testListNotificationsDescendentNoMoreResults() {
		$maxResults = EndpointV2Controller::ENFORCED_LIST_LIMIT;
		$notificationList = $this->getNotificationList();
		krsort($notificationList);
		$notificationList = array_slice($notificationList, 0, $maxResults + 1, true);

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->method('getUID')
			->willReturn('test_user1');
		$this->userSession->method('getUser')
			->willReturn($user);

		$this->handler->method('getMaxNotificationId')
			->willReturn(123);  // purposely use a different max id than the one returned in the list
		$this->handler->method('fetchDescendentList')
			->with('test_user1', null, $maxResults + 1, $this->anything())
			->willReturn(array_slice($notificationList, 0, 3, true));

		$this->manager->method('prepare')
			->will($this->returnArgument(0));

		$ocsResult = $this->controller->listNotifications();
		$this->assertEquals(Http::STATUS_OK, $ocsResult->getStatus());

		$rawData = json_decode($ocsResult->render(), true);
		$rawData = $rawData['ocs']['data'];
		$this->assertLessThan($maxResults, count($rawData['notifications']));
		$this->assertTrue($this->isKeySortedTopToBottom($rawData['notifications']));
		$this->assertArrayNotHasKey('next', $rawData);

		$resultHeaders = $ocsResult->getHeaders();
		$this->assertArrayHasKey('OC-Last-Notification', $resultHeaders);
		$this->assertEquals(123, $resultHeaders['OC-Last-Notification']);
	}

	public function testGetNotificationWrongUser() {
		$this->userSession->method('getUser')
			->willReturn(null);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $this->controller->getNotification(5)->getStatus());
	}

	public function testGetNotificationNotNotificationType() {
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->method('getUID')
			->willReturn('test_user1');
		$this->userSession->method('getUser')
			->willReturn($user);

		$this->handler->method('getById')
			->willReturn(false);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $this->controller->getNotification(5)->getStatus());
	}

	public function testGetNotificationCannotPrepare() {
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->method('getUID')
			->willReturn('test_user1');
		$this->userSession->method('getUser')
			->willReturn($user);

		$notification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();
		$notification->method('getObjectType')
			->willReturn('test_notification');
		$notification->method('getObjectId')
			->willReturn('21');
		$notification->method('getDateTime')
			->willReturn(new \DateTime());
		$notification->method('getParsedActions')
			->willReturn([]);

		$this->handler->method('getById')
			->willReturn($notification);

		$this->manager->method('prepare')
			->will($this->throwException(new \InvalidArgumentException()));

		$this->assertEquals(Http::STATUS_NOT_FOUND, $this->controller->getNotification(5)->getStatus());
	}

	public function testGetNotification() {
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->method('getUID')
			->willReturn('test_user1');
		$this->userSession->method('getUser')
			->willReturn($user);

		$datetime = new \DateTime();
		$notification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();
		$notification->method('getObjectType')
			->willReturn('test_notification');
		$notification->method('getObjectId')
			->willReturn('21');
		$notification->method('getDateTime')
			->willReturn($datetime);
		$notification->method('getParsedSubject')
			->willReturn('the parsed subject');
		$notification->method('getParsedActions')
			->willReturn([]);

		$this->handler->method('getById')
			->willReturn($notification);

		$this->manager->method('prepare')
			->willReturn($notification);

		$ocsResult = $this->controller->getNotification(5);
		$this->assertEquals(Http::STATUS_OK, $ocsResult->getStatus());

		$rawData = json_decode($ocsResult->render(), true);
		$rawData = $rawData['ocs']['data'];
		$this->assertEquals('5', $rawData['notification_id']);
		$this->assertEquals($datetime->format('c'), $rawData['datetime']);
		$this->assertEquals('the parsed subject', $rawData['subject']);
		$this->assertEquals([], $rawData['actions']);
	}

	public function testDeleteNotificationWrongUser() {
		$this->userSession->method('getUser')
			->willReturn(null);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $this->controller->deleteNotification(5)->getStatus());
	}

	public function testDeleteNotification() {
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->method('getUID')
			->willReturn('test_user1');
		$this->userSession->method('getUser')
			->willReturn($user);

		$this->assertEquals(Http::STATUS_OK, $this->controller->deleteNotification(5)->getStatus());
	}

	public function testGetLastNotificationIdWrongUser() {
		$this->userSession->method('getUser')
			->willReturn(null);

		$this->assertEquals(Http::STATUS_FORBIDDEN, $this->controller->getLastNotificationId(5)->getStatus());
	}

	public function testGetLastNotification() {
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->method('getUID')
			->willReturn('test_user1');
		$this->userSession->method('getUser')
			->willReturn($user);

		$this->handler->method('getMaxNotificationId')
			->willReturn(321);

		$ocsResult = $this->controller->getLastNotificationId();
		$this->assertEquals(Http::STATUS_OK, $ocsResult->getStatus());

		$rawData = json_decode($ocsResult->render(), true);
		$rawData = $rawData['ocs']['data'];
		$this->assertEquals(['id' => 321], $rawData);

		$resultHeaders = $ocsResult->getHeaders();
		$this->assertArrayHasKey('OC-Last-Notification', $resultHeaders);
		$this->assertEquals(321, $resultHeaders['OC-Last-Notification']);
	}

	public function testGetLastNotificationNoNotifications() {
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->method('getUID')
			->willReturn('test_user1');
		$this->userSession->method('getUser')
			->willReturn($user);

		$this->handler->method('getMaxNotificationId')
			->willReturn(null);

		$ocsResult = $this->controller->getLastNotificationId();
		$this->assertEquals(Http::STATUS_OK, $ocsResult->getStatus());

		$rawData = json_decode($ocsResult->render(), true);
		$rawData = $rawData['ocs']['data'];
		$this->assertEquals(['id' => -1], $rawData);

		$resultHeaders = $ocsResult->getHeaders();
		$this->assertArrayHasKey('OC-Last-Notification', $resultHeaders);
		$this->assertEquals(-1, $resultHeaders['OC-Last-Notification']);
	}

	public function testPrepareNotification() {
		$notification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();
		$this->manager->method('prepare')
			->will($this->returnArgument(0));
		$this->assertInstanceOf(INotification::class, $this->controller->prepareNotification($notification, 'en_US'));
	}

	public function testPrepareNotificationInvalid() {
		$notification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();
		$this->manager->method('prepare')
			->will($this->throwException(new \InvalidArgumentException()));
		$this->assertNull($this->controller->prepareNotification($notification, 'en_US'));
	}
}
