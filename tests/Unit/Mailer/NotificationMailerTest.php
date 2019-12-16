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

use OC\Mail\Mailer;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\L10N\IFactory;
use OCP\IL10N;
use OCA\Notifications\Configuration\OptionsStorage;
use OCA\Notifications\Mailer\NotificationMailer;

class NotificationMailerTest extends \Test\TestCase {
	/** @var IManager */
	private $manager;
	/** @var Mailer */
	private $mailer;
	/** @var OptionsStorage */
	private $optionsStorage;
	/** @var IFactory */
	private $l10nFactory;
	/** @var NotificationMailer*/
	private $notificationMailer;

	protected function setUp(): void {
		parent::setUp();

		$this->manager = $this->getMockBuilder(IManager::class)
			->disableOriginalConstructor()
			->getMock();

		$this->mailer = $this->getMockBuilder(Mailer::class)
			->setMethodsExcept(['createMessage'])
			->disableOriginalConstructor()
			->getMock();

		$this->optionsStorage = $this->getMockBuilder(OptionsStorage::class)
			->disableOriginalConstructor()
			->getMock();

		$this->l10nFactory = $this->getMockBuilder(IFactory::class)
			->disableOriginalConstructor()
			->getMock();

		$this->notificationMailer = new NotificationMailer($this->manager, $this->mailer, $this->optionsStorage, $this->l10nFactory);
	}

	public function emailProvider() {
		return [
			['a@test.com'],
			['a@x.invalid.com'],
			['oöGm41l@test.com'],
			['@b.test.com'],
			[''],
			[null],
		];
	}

	/**
	 * @dataProvider emailProvider
	 */
	public function testValidateEmail($email) {
		$pattern = '/^[a-zA-Z0-9][a-zA-Z0-9]*@test\.com$/';

		$this->mailer->method('validateMailAddress')
			->will($this->returnCallback(function ($email) use ($pattern) {
				return \preg_match($pattern, $email) === 1;
			}));

		$this->assertEquals(\preg_match($pattern, $email) === 1, $this->notificationMailer->validateEmail($email));
	}

	public function testSendNotification() {
		$mockedNotification = $this->getMockBuilder(INotification::class)->disableOriginalConstructor()->getMock();
		$mockedNotification->method('getUser')->willReturn('userTest1');
		$mockedNotification->method('getObjectType')->willReturn('test_obj_type');
		$mockedNotification->method('getObjectId')->willReturn('202');
		$mockedNotification->method('getParsedSubject')->willReturn('This is a parsed subject');
		$mockedNotification->method('getParsedMessage')->willReturn('Parsed message is this');
		$mockedNotification->method('getLink')->willReturn('');

		$this->manager->method('prepare')->willReturn($mockedNotification);

		$mockedL10N = $this->getMockBuilder(IL10N::class)->disableOriginalConstructor()->getMock();
		$mockedL10N->method('t')
			->will($this->returnCallback(function ($text, $params) {
				return \vsprintf($text, $params);
			}));

		$this->l10nFactory->method('get')->willReturn($mockedL10N);
		$this->mailer->expects($this->once())->method('send');

		$this->optionsStorage->method('getOptions')
			->with('userTest1')
			->willReturn(['email_sending_option' => 'always']);

		$sentMessage = $this->notificationMailer->sendNotification($mockedNotification, 'http://test.server/oc', 'test@example.com');

		$this->assertEquals(['test@example.com' => null], $sentMessage->getTo());
		// check that the notification subject is the email subject
		$this->assertEquals('This is a parsed subject', $sentMessage->getSubject());

		// notification's subject and message must be present in the email body, as well as the server url
		$this->assertContains($mockedNotification->getParsedMessage(), $sentMessage->getPlainBody());
		$this->assertContains('http://test.server/oc', $sentMessage->getPlainBody());
	}

	/**
	 */
	public function testSendNotificationFailedRecipients() {
		$this->expectException(\Exception::class);

		$mockedNotification = $this->getMockBuilder(INotification::class)->disableOriginalConstructor()->getMock();
		$mockedNotification->method('getUser')->willReturn('userTest1');
		$mockedNotification->method('getObjectType')->willReturn('test_obj_type');
		$mockedNotification->method('getObjectId')->willReturn('202');
		$mockedNotification->method('getParsedSubject')->willReturn('This is a parsed subject');
		$mockedNotification->method('getParsedMessage')->willReturn('Parsed message is this');

		$this->manager->method('prepare')->willReturn($mockedNotification);

		$mockedL10N = $this->getMockBuilder(IL10N::class)->disableOriginalConstructor()->getMock();
		$mockedL10N->method('t')
			->will($this->returnCallback(function ($text, $params) {
				return \vsprintf($text, $params);
			}));

		$this->l10nFactory->method('get')->willReturn($mockedL10N);
		$this->mailer->expects($this->once())
			->method('send')
			->willReturn(['userTest1']);

		$this->optionsStorage->method('getOptions')
			->with('userTest1')
			->willReturn(['email_sending_option' => 'always']);

		$sentMessage = $this->notificationMailer->sendNotification($mockedNotification, 'http://test.server/oc', 'test@example.com');
	}

	public function testSendNotificationPrevented() {
		$mockedNotification = $this->getMockBuilder(INotification::class)->disableOriginalConstructor()->getMock();
		$mockedNotification->method('getUser')->willReturn('userTest1');
		$mockedNotification->method('getObjectType')->willReturn('test_obj_type');
		$mockedNotification->method('getObjectId')->willReturn('202');
		$mockedNotification->method('getParsedSubject')->willReturn('This is a parsed subject');
		$mockedNotification->method('getParsedMessage')->willReturn('Parsed message is this');

		$this->manager->method('prepare')->willReturn($mockedNotification);

		$this->optionsStorage->method('getOptions')
			->with('userTest1')
			->willReturn(['email_sending_option' => 'never']);

		$sentMessage = $this->notificationMailer->sendNotification($mockedNotification, 'http://test.server/oc', ['test@example.com']);
		$this->assertFalse($sentMessage);
	}

	public function willSendNotificationProvider() {
		$mockedAction = $this->getMockBuilder(IAction::class)
			->disableOriginalConstructor()
			->getMock();
		$mockedNotification = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();
		$mockedNotification->method('getUser')->willReturn('userTest1');
		$mockedNotification->method('getObjectType')->willReturn('test_obj_type');
		$mockedNotification->method('getObjectId')->willReturn('202');
		$mockedNotification->method('getParsedSubject')->willReturn('This is a parsed subject');
		$mockedNotification->method('getParsedMessage')->willReturn('Parsed message is this');
		$mockedNotification->method('getActions')->willReturn([$mockedAction]);

		$mockedNotification2 = $this->getMockBuilder(INotification::class)
			->disableOriginalConstructor()
			->getMock();
		$mockedNotification2->method('getUser')->willReturn('userTest1');
		$mockedNotification2->method('getObjectType')->willReturn('test_obj_type');
		$mockedNotification2->method('getObjectId')->willReturn('202');
		$mockedNotification2->method('getParsedSubject')->willReturn('This is a parsed subject');
		$mockedNotification2->method('getParsedMessage')->willReturn('Parsed message is this');

		return [
			[$mockedNotification, 'never', false],
			[$mockedNotification, 'always', true],
			[$mockedNotification, 'action', true],
			[$mockedNotification, 'randomMissing', false],
			[$mockedNotification2, 'never', false],
			[$mockedNotification2, 'always', true],
			[$mockedNotification2, 'action', false],
			[$mockedNotification2, 'randomMissing', false],
		];
	}

	/**
	 * @dataProvider willSendNotificationProvider
	 */
	public function testWillSendNotification($notification, $configOption, $expectedValue) {
		$this->optionsStorage->method('getOptions')
			->with('userTest1')
			->willReturn(['email_sending_option' => $configOption]);

		$this->assertEquals($expectedValue, $this->notificationMailer->willSendNotification($notification));
	}
}
