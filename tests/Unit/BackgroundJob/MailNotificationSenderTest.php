<?php
/**
 * @author Sujith Haridasan <sharidasan@owncloud.com>
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

namespace OCA\Notifications\Tests\BackgroundJob;

use OC\Group\Group;
use OC\Notification\Action;
use OC\User\Manager;
use OCA\Notifications\BackgroundJob\MailNotificationSender;
use OCA\Notifications\Mailer\NotificationMailerAdapter;
use OCA\Notifications\Tests\Unit\TestCase;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IMailer;
use OCP\Notification\INotification;
use OCP\Share\IManager;
use OCP\Notification\IManager as NotificationManager;
use OCP\Share\IShare;

class MailNotificationSenderTest extends TestCase {

	/** @var \OCP\Share\IManager | \PHPUnit_Framework_MockObject_MockObject */
	private $shareManager;

	/** @var \OCP\IGroupManager | \PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;

	/** @var \OC\User\Manager | \PHPUnit_Framework_MockObject_MockObject */
	private $userManager;

	/** @var NotificationManager | \PHPUnit_Framework_MockObject_MockObject */
	private $notificationManager;

	/** @var \OCA\Notifications\Mailer\NotificationMailerAdapter | \PHPUnit_Framework_MockObject_MockObject */
	private $mailerAdapter;

	/** @var \OCP\Mail\IMailer | \PHPUnit_Framework_MockObject_MockObject */
	private $mailer;

	/** @var \OCP\IConfig | \PHPUnit_Framework_MockObject_MockObject */
	private $config;

	/** @var \OCP\IURLGenerator | \PHPUnit_Framework_MockObject_MockObject */
	private $urlGenerator;

	/** @var \OCP\IRequest | \PHPUnit_Framework_MockObject_MockObject */
	private $request;

	/** @var \OCP\ILogger | \PHPUnit_Framework_MockObject_MockObject */
	private $logger;

	/** @var \OCA\Notifications\BackgroundJob\MailNotificationSender | \PHPUnit_Framework_MockObject_MockObject */
	private $mailNotificationSender;

	protected function setUp() {
		parent::setUp();

		$this->shareManager = $this->createMock(IManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(Manager::class);
		$this->notificationManager = $this->createMock(NotificationManager::class);
		$this->mailerAdapter = $this->createMock(NotificationMailerAdapter::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->config = $this->createMock(IConfig::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->request = $this->createMock(IRequest::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->mailNotificationSender = new MailNotificationSender($this->shareManager, $this->groupManager,
			$this->userManager, $this->notificationManager, $this->mailerAdapter, $this->mailer, $this->config,
			$this->urlGenerator, $this->request, $this->logger);
	}

	protected function tearDown() {
		parent::tearDown();
	}

	private function createShare() {
		$node = $this->createMock(Node::class);
		$node->method('getId')->willReturn(4000);
		$node->method('getName')->willReturn('node-name');

		$share = $this->createMock(IShare::class);
		$share->method('getId')->willReturn(12300);
		$share->method('getShareOwner')->willReturn('shareOwner');
		$share->method('getSharedBy')->willReturn('sharedBy');
		$share->method('getNode')->willReturn($node);

		return $share;
	}

	private function makeGroup($groupName, $members) {
		$memberObjects = \array_map(function ($memberName) {
			$memberObject = $this->createMock(IUser::class);
			$memberObject->method('getUID')->willReturn($memberName);
			$memberObject->method('getEmailAddress')->willReturn('foo@bar.com');
			return $memberObject;
		}, $members);

		$group = $this->createMock(IGroup::class);
		$group->expects($this->once())
			->method('getUsers')
			->willReturn($memberObjects);

		$this->groupManager->expects($this->any())
			->method('get')
			->with($groupName)
			->willReturn($group);

		return $memberObjects;
	}

	public function testGroupNotification() {
		$share = $this->createShare();
		$share->expects($this->exactly(1))
			->method('getShareType')
			->willReturn(\OCP\Share::SHARE_TYPE_GROUP);
		$share->expects($this->once())
			->method('getSharedWith')
			->willReturn('group1');

		$userIds = [];
		for ($i = 1; $i <= 105; $i++) {
			$userIds[] = 'user' . (string) $i;
		}
		$iUsers = $this->makeGroup('group1', $userIds);

		$group = $this->createMock(Group::class);
		$this->groupManager->expects($this->once())
			->method('get')
			->willReturn($group);

		$this->shareManager->expects($this->once())
			->method('getShareById')
			->willReturn($share);

		$this->request->expects($this->exactly(105))
			->method('getServerProtocol')
			->willReturn('http');
		$this->request->expects($this->exactly(105))
			->method('getServerHost')
			->willReturn('foo');

		$this->urlGenerator->expects($this->exactly(105))
			->method('linkToRouteAbsolute')
			->willReturn('http://foo/bar/index.php/f/22');

		$action = $this->createMock(Action::class);

		$inotification = $this->createMock(INotification::class);
		$this->notificationManager->expects($this->exactly(105))
			->method('createNotification')
			->willReturn($inotification);

		$inotification->expects($this->exactly(105))
			->method('setApp')
			->willReturn($inotification);
		$inotification->expects($this->exactly(105))
			->method('setUser')
			->willReturn($inotification);
		$inotification->expects($this->exactly(105))
			->method('setDateTime')
			->willReturn($inotification);
		$inotification->expects($this->exactly(105))
			->method('setObject')
			->willReturn($inotification);
		$inotification->expects($this->exactly(105))
			->method('setLink')
			->willReturn($inotification);
		$inotification->expects($this->exactly(105))
			->method('setSubject')
			->willReturn($inotification);
		$inotification->expects($this->exactly(105))
			->method('setMessage')
			->willReturn($inotification);
		$inotification->expects($this->exactly(105))
			->method('createAction')
			->willReturn($action);
		$inotification->expects($this->exactly(105))
			->method('addAction')
			->willReturnMap([
				[$action, $inotification],
			]);

		for ($i = 1; $i <= 105; $i++) {
			$action->expects($this->exactly(105))
				->method('setLabel')
				->willReturn($action);
			$action->expects($this->exactly(105))
				->method('setLink')
				->willReturn($action);

			$this->mailerAdapter->expects($this->exactly(105))
				->method('sendMail')
				->with($inotification);
		}

		$this->mailNotificationSender->sendNotify('ocinternal:' . $share->getId());
	}

	public function testSingleUserShareNotification() {
		$share = $this->createShare();
		$share->expects($this->exactly(2))
			->method('getShareType')
			->willReturn(\OCP\Share::SHARE_TYPE_USER);

		$this->request->expects($this->exactly(1))
			->method('getServerProtocol')
			->willReturn('http');
		$this->request->expects($this->exactly(1))
			->method('getServerHost')
			->willReturn('foo');

		$this->urlGenerator->expects($this->once())
			->method('linkToRouteAbsolute')
			->willReturn('http://foo/bar/index.php/f/22');

		$emailAction = $this->createMock(Action::class);
		$emailAction->expects($this->once())
			->method('setLabel')
			->willReturn($emailAction);
		$emailAction->expects($this->once())
			->method('setLink')
			->willReturn($emailAction);


		$inotification = $this->createMock(INotification::class);
		$inotification->expects($this->once())
			->method('setApp')
			->willReturn($inotification);
		$inotification->expects($this->once())
			->method('setUser')
			->willReturn($inotification);
		$inotification->expects($this->once())
			->method('setDateTime')
			->willReturn($inotification);
		$inotification->expects($this->once())
			->method('setObject')
			->willReturn($inotification);
		$inotification->expects($this->once())
			->method('setLink')
			->willReturn($inotification);
		$inotification->expects($this->once())
			->method('setSubject')
			->willReturn($inotification);
		$inotification->expects($this->once())
			->method('setMessage')
			->willReturn($inotification);
		$inotification->expects($this->once())
			->method('createAction')
			->willReturn($emailAction);
		$inotification->expects($this->once())
			->method('addAction')
			->willReturn($inotification);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($inotification);

		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getEMailAddress')
			->willReturn('foo@bar.com');
		$this->userManager->expects($this->once())
			->method('get')
			->willReturn($user);

		$this->shareManager->expects($this->once())
			->method('getShareById')
			->willReturn($share);
		$this->mailNotificationSender->sendNotify($share->getId());
	}
}
