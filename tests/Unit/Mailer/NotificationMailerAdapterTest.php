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

namespace OCA\Notifications\Tests\Unit\Mailer;

use OCP\IUserManager;
use OCP\IUser;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\Notification\INotification;
use OCP\Notification\IAction;
use OCA\Notifications\Mailer\NotificationMailer;
use OCA\Notifications\Mailer\NotificationMailerAdapter;

class NotificationMailerAdapterTest extends \Test\TestCase {
	/** @var NotificationMailer */
	private $notificationMailer;
	/** @var IUserManager */
	private $userManager;
	/** @var ILogger */
	private $logger;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var NotificationMailerAdapter */
	private $adapter;

	protected function setUp() {
		parent::setUp();

		$this->notificationMailer = $this->getMockBuilder(NotificationMailer::class)
			->disableOriginalConstructor()
			->getMock();

		$this->userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();

		$this->logger = $this->getMockBuilder(ILogger::class)
			->disableOriginalConstructor()
			->getMock();

		$this->urlGenerator = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()
			->getMock();

		$this->adapter = new NotificationMailerAdapter($this->notificationMailer, $this->userManager, $this->logger, $this->urlGenerator);
	}

	public function testSendMailWontSend() {
		$mockedNotification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();

		$mockedNotification->method('getActions')->willReturn([]);
		$mockedNotification->method('getObjectType')->willReturn('testobject');
		$mockedNotification->method('getObjectId')->willReturn('467');
		$mockedNotification->method('getUser')->willReturn('missingUser');

		$this->logger->expects($this->once())
			->method('debug')
			->with($this->stringContains('personal configuration for missingUser prevents it'));

		$this->notificationMailer->method('willSendNotification')->willReturn(false);
		$this->notificationMailer->expects($this->never())
			->method('sendNotification');

		$this->adapter->sendMail($mockedNotification);
	}

	public function testSendMailMissingUser() {
		$mockedAction = $this->getMockBuilder(IAction::class)
			->disableOriginalConstructor()
			->getMock();

		$mockedNotification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();

		$mockedNotification->method('getActions')->willReturn([$mockedAction]);
		$mockedNotification->method('getObjectType')->willReturn('testobject');
		$mockedNotification->method('getObjectId')->willReturn('467');
		$mockedNotification->method('getUser')->willReturn('missingUser');

		$this->userManager->method('get')
			->with('missingUser')
			->willReturn(null);

		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('testobject#467 can\'t be sent'));

		$this->notificationMailer->method('willSendNotification')->willReturn(true);
		$this->notificationMailer->expects($this->never())
			->method('sendNotification');

		$this->adapter->sendMail($mockedNotification);
	}

	public function testNotifyMissingEmail() {
		$mockedUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$mockedUser->method('getEMailAddress')
			->willReturn(null);

		$mockedAction = $this->getMockBuilder(IAction::class)
			->disableOriginalConstructor()
			->getMock();

		$mockedNotification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();

		$mockedNotification->method('getActions')->willReturn([$mockedAction]);
		$mockedNotification->method('getObjectType')->willReturn('testobject');
		$mockedNotification->method('getObjectId')->willReturn('467');
		$mockedNotification->method('getUser')->willReturn('validUser');

		$this->userManager->method('get')
			->with('validUser')
			->willReturn($mockedUser);

		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('testobject#467 can\'t be sent'));

		$this->notificationMailer->method('willSendNotification')->willReturn(true);
		$this->notificationMailer->expects($this->never())
			->method('sendNotification');

		$this->adapter->sendMail($mockedNotification);
	}

	public function testNotifyInvalidEmail() {
		$mockedUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$mockedUser->method('getEMailAddress')
			->willReturn('wiiiiii');

		$mockedAction = $this->getMockBuilder(IAction::class)
			->disableOriginalConstructor()
			->getMock();

		$mockedNotification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();

		$mockedNotification->method('getActions')->willReturn([$mockedAction]);
		$mockedNotification->method('getObjectType')->willReturn('testobject');
		$mockedNotification->method('getObjectId')->willReturn('467');
		$mockedNotification->method('getUser')->willReturn('validUser');

		$this->userManager->method('get')
			->with('validUser')
			->willReturn($mockedUser);

		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('testobject#467 can\'t be sent'));

		$this->notificationMailer->method('willSendNotification')->willReturn(true);
		$this->notificationMailer->method('validateEmail')
			->willReturn(false);
		$this->notificationMailer->expects($this->never())
			->method('sendNotification');

		$this->adapter->sendMail($mockedNotification);
	}

	public function testNotify() {
		$mockedUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$mockedUser->method('getEMailAddress')
			->willReturn('we@we.we');

		$mockedAction = $this->getMockBuilder(IAction::class)
			->disableOriginalConstructor()
			->getMock();

		$mockedNotification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();

		$mockedNotification->method('getActions')->willReturn([$mockedAction]);
		$mockedNotification->method('getObjectType')->willReturn('testobject');
		$mockedNotification->method('getObjectId')->willReturn('467');
		$mockedNotification->method('getUser')->willReturn('validUser');

		$this->userManager->method('get')
			->with('validUser')
			->willReturn($mockedUser);

		$this->urlGenerator->method('getAbsoluteURL')
			->with('/')
			->willReturn('http://what.ever/oc');

		$this->logger->expects($this->never())
			->method('warning');
		$this->logger->expects($this->never())
			->method('error');
		$this->logger->expects($this->never())
			->method('critical');

		$this->notificationMailer->method('willSendNotification')->willReturn(true);
		$this->notificationMailer->method('validateEmail')
			->willReturn(true);
		$this->notificationMailer->expects($this->once())
			->method('sendNotification')
			->with($mockedNotification, 'http://what.ever/oc', 'we@we.we');

		$this->adapter->sendMail($mockedNotification);
	}

	public function testNotifyWithLink() {
		$mockedUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$mockedUser->method('getEMailAddress')
			->willReturn('we@we.we');

		$mockedAction = $this->getMockBuilder(IAction::class)
			->disableOriginalConstructor()
			->getMock();

		$mockedNotification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();

		$mockedNotification->method('getActions')->willReturn([$mockedAction]);
		$mockedNotification->method('getObjectType')->willReturn('testobject');
		$mockedNotification->method('getObjectId')->willReturn('467');
		$mockedNotification->method('getUser')->willReturn('validUser');
		$mockedNotification->method('getLink')->willReturn('http://notification.com/link/here');

		$this->userManager->method('get')
			->with('validUser')
			->willReturn($mockedUser);

		$this->urlGenerator->method('getAbsoluteURL')
			->with('/')
			->willReturn('http://what.ever/oc');

		$this->logger->expects($this->never())
			->method('warning');
		$this->logger->expects($this->never())
			->method('error');
		$this->logger->expects($this->never())
			->method('critical');

		$this->notificationMailer->method('willSendNotification')->willReturn(true);
		$this->notificationMailer->method('validateEmail')
			->willReturn(true);
		$this->notificationMailer->expects($this->once())
			->method('sendNotification')
			->with($mockedNotification, 'http://notification.com/link/here', 'we@we.we');

		$this->adapter->sendMail($mockedNotification);
	}

	public function testNotifySendException() {
		$mockedUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$mockedUser->method('getEMailAddress')
			->willReturn('we@we.we');

		$mockedAction = $this->getMockBuilder(IAction::class)
			->disableOriginalConstructor()
			->getMock();

		$mockedNotification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();

		$mockedNotification->method('getActions')->willReturn([$mockedAction]);
		$mockedNotification->method('getObjectType')->willReturn('testobject');
		$mockedNotification->method('getObjectId')->willReturn('467');
		$mockedNotification->method('getUser')->willReturn('validUser');

		$this->userManager->method('get')
			->with('validUser')
			->willReturn($mockedUser);

		$this->urlGenerator->method('getAbsoluteURL')
			->with('/')
			->willReturn('http://what.ever/oc');

		$this->logger->expects($this->once())
			->method('logException');

		$this->notificationMailer->method('willSendNotification')->willReturn(true);
		$this->notificationMailer->method('validateEmail')
			->willReturn(true);
		$this->notificationMailer->expects($this->once())
			->method('sendNotification')
			->with($mockedNotification, 'http://what.ever/oc', 'we@we.we')
			->will($this->throwException(new \Exception()));

		$this->adapter->sendMail($mockedNotification);
	}
}
