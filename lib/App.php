<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
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

namespace OCA\Notifications;


use OCP\BackgroundJob\IJobList;
use OCP\Notification\IApp;
use OCP\Notification\INotification;
use OCA\Notifications\Mailer\NotificationMailerAdapter;

class App implements IApp {
	/** @var Handler */
	protected $handler;

	/** @var NotificationMailerAdapter */
	protected $mailerAdapter;

	/** @var IJobList */
	protected $jobList;

	public function __construct(Handler $handler, NotificationMailerAdapter $mailerAdapter, IJobList $jobList) {
		$this->handler = $handler;
		$this->mailerAdapter = $mailerAdapter;
		$this->jobList = $jobList;
	}

	/**
	 * @param INotification $notification
	 * @return null
	 * @throws \InvalidArgumentException When the notification is not valid
	 * @since 8.2.0
	 */
	public function notify(INotification $notification) {
		$this->handler->add($notification);
	}

	/**
	 * Notification of a share to user(s) by email is achieved by background job
	 * @param string $shareFullId
	 * @return null
	 */
	public function emailNotify($shareFullId) {
		$this->jobList->add('OCA\Notifications\BackgroundJob\MailNotificationSender',
			[
				'shareFullId' => $shareFullId,
				'webroot' => \OC::$WEBROOT //This is required to get the url link correctly
			]);
	}

	/**
	 * @param INotification $notification
	 * @return int
	 * @since 8.2.0
	 */
	public function getCount(INotification $notification) {
		return $this->handler->count($notification);
	}

	/**
	 * @param INotification $notification
	 * @return null
	 * @since 8.2.0
	 */
	public function markProcessed(INotification $notification) {
		$this->handler->delete($notification);
	}
}
