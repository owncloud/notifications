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

namespace OCA\Notifications\BackgroundJob;

use OC\BackgroundJob\Job;
use OC\User\Manager;
use OCA\Notifications\Configuration\OptionsStorage;
use OCA\Notifications\Mailer\NotificationMailer;
use OCA\Notifications\Mailer\NotificationMailerAdapter;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Mail\IMailer;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Notification\IManager as NotificationManager;

/**
 * Class MailNotificationSender
 *
 * @package OCA\Notifications\BackgroundJob
 */
class MailNotificationSender extends Job {

	/** @var IManager  */
	private $shareManager;

	/** @var \OC\Group\Manager|IGroupManager  */
	private $groupManager;

	/** @var Manager  */
	private $userManager;

	/** @var NotificationManager  */
	private $notificationManager;

	/** @var IConfig  */
	private $config;

	/** @var IMailer  */
	private $mailer;

	/** @var NotificationMailerAdapter  */
	private $mailerAdapter;

	/** @var IURLGenerator  */
	private $urlGenerator;

	/** @var IRequest  */
	private $request;

	/** @var ILogger */
	private $logger;

	public function __construct(IManager $shareManager = null,
								IGroupManager $groupManager = null,
								Manager $userManager = null,
								NotificationManager $notificationManager = null,
								NotificationMailerAdapter $mailerAdapter = null,
								IMailer $mailer = null,
								IConfig $config = null,
								IURLGenerator $urlGenerator = null,
								IRequest $request = null,
								ILogger $logger = null) {
		$this->shareManager = $shareManager ? $shareManager : \OC::$server->getShareManager();
		$this->groupManager = $groupManager ? $groupManager : \OC::$server->getGroupManager();
		$this->userManager = $userManager ? $userManager : \OC::$server->getUserManager();
		$this->notificationManager = $notificationManager ? $notificationManager : \OC::$server->getNotificationManager();
		$this->urlGenerator = $urlGenerator ? $urlGenerator : \OC::$server->getURLGenerator();
		$this->request = $request ? $request : \OC::$server->getRequest();
		$this->logger = $logger ? $logger : \OC::$server->getLogger();
		$this->mailer = $mailer ? $mailer : \OC::$server->getMailer();
		$this->config = $config ? $config : \OC::$server->getConfig();

		$optionsStorage = new OptionsStorage($this->config);
		$notificationMailer = new NotificationMailer($this->notificationManager, $this->mailer, $optionsStorage);

		$this->mailerAdapter = $mailerAdapter ? $mailerAdapter :
			new NotificationMailerAdapter($notificationMailer, $this->userManager, $this->logger, $this->urlGenerator);
	}

	/**
	 * @param $notificationURL
	 * @return string
	 */
	public function fixURL($notificationURL) {
		$url = $this->request->getServerProtocol() . '://' . $this->request->getServerHost();
		$partURL = \explode($url, $notificationURL)[1];
		$webRoot = $this->getArgument()['webroot'];
		if (\strpos($partURL, $webRoot) === false) {
			$notificationURL = $url . $this->getArgument()['webroot'] . $partURL;
		}
		return $notificationURL;
	}

	/**
	 * @param $shareFullId
	 */
	public function sendNotify($shareFullId) {
		$notificationList = [];
		$maxCountSendNotification = 100;

		//Check if its an internal share or not
		try {
			$share = $this->shareManager->getShareById($shareFullId);
		} catch (ShareNotFound $e) {
			return null;
		}

		$users = [];
		if ($share->getShareType() === \OCP\Share::SHARE_TYPE_GROUP) {
			//Notify all the group members
			$group = $this->groupManager->get($share->getSharedWith());
			$users = $group->getUsers();
		} elseif ($share->getShareType() === \OCP\Share::SHARE_TYPE_USER) {
			$users[] = $this->userManager->get($share->getSharedWith());
		}

		foreach ($users as $user) {
			if ($user->getEMailAddress() === null) {
				\OC::$server->getLogger()->warning("Mail notification can not be sent to " . $user->getUID() . " for share " . $share->getName() .  " as email for user is not set");
				continue;
			}
			$notification = $this->notificationManager->createNotification();
			$notification->setApp('files_sharing')
				->setUser($user->getUID())
				->setDateTime(new \DateTime())
				->setObject('local_share', $shareFullId);

			$fileLink = $this->urlGenerator->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileId' => $share->getNode()->getId()]);

			$fileLink = $this->fixURL($fileLink);
			$notification->setLink($fileLink);

			$notification->setSubject('local_share', [$share->getShareOwner(), $share->getSharedBy(), $share->getNode()->getName()]);
			$notification->setMessage('local_share', [$share->getShareOwner(), $share->getSharedBy(), $share->getNode()->getName()]);

			//Adding action because sendMail requires an action for the notificaiton
			$acceptAction = $notification->createAction();
			$acceptAction->setLabel('email');
			$acceptAction->setLink($fileLink, 'POST');
			$notification->addAction($acceptAction);

			if (\count($notificationList) < $maxCountSendNotification) {
				$notificationList[] = $notification;
			} else {
				foreach ($notificationList as $notificationDispatch) {
					$this->mailerAdapter->sendMail($notificationDispatch);
				}

				//Once users in notificationList recieve notification reset the list
				$notificationList = [$notification];
			}
		}

		if (\count($notificationList) < $maxCountSendNotification) {
			foreach ($notificationList as $notificationDispatch) {
				$this->mailerAdapter->sendMail($notificationDispatch);
			}
		}
	}

	protected function run($argument) {
		if (($this->getArgument()['shareFullId'] === null) || ($this->getArgument()['webroot'] === null)) {
			$this->logger->debug(__METHOD__ . " To execute this background job shareFullId and webroot are required. Aborting the job");
		} else {
			$this->sendNotify($this->getArgument()['shareFullId']);
		}
		\OC::$server->getJobList()->removeById($this->getId());
	}
}
