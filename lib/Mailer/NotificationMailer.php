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

namespace OCA\Notifications\Mailer;

use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Mail\IMailer;
use OCP\IConfig;
use OCP\L10N\IFactory;
use OCP\Util;
use OCP\Template;
use OCA\Notifications\Configuration\OptionsStorage;

/**
 * The class will focus on sending notifications via email. In addition, some email-related
 * functions have been added.
 */
class NotificationMailer {
	/** @var IMailer */
	private $mailer;

	/** @var IManager */
	private $manager;

	/** @var OptionsStorage */
	private $optionsStorage;

	/** @var IFactory */
	private $l10nFactory;

	public function __construct(IManager $manager, IMailer $mailer, OptionsStorage $optionsStorage, IFactory $l10nFactory) {
		$this->manager = $manager;
		$this->mailer = $mailer;
		$this->optionsStorage = $optionsStorage;
		$this->l10nFactory = $l10nFactory;
	}

	/**
	 * Send a notification via email to the list of email addresses passed as parameter
	 * @param INotification $notification the notification to be sent
	 * @param string $serverUrl the url of the server so the user can access to his instance from the
	 * email. Make sure the url is safe to be used as a clickable link (in case encoding is needed)
	 * @param string $emailAddress the  email addresses where the notification should be sent.
	 * @return \OC\Mail\Message|bool the message sent, or false if the mail isn't sent
	 * @throws \Exception if the mail couldn't be sent or some recipients didn't
	 * receive the mail (according to \OCP\Mail\IMailer::send method)
	 */
	public function sendNotification(INotification $notification, $serverUrl, $emailAddress) {
		if (!$this->willSendNotification($notification)) {
			return false;
		}

		$targetUser = $notification->getUser();
		$language = $this->optionsStorage->getUserLanguage($targetUser);

		$notification = $this->manager->prepare($notification, $language);

		$emailMessage = $this->mailer->createMessage();
		$emailMessage->setTo([$emailAddress]);

		$l10n = $this->l10nFactory->get('notifications', $language);

		$notificationObjectType = $notification->getObjectType();
		$notificationObjectId = $notification->getObjectId();
		$generatedId = "$notificationObjectType#$notificationObjectId";

		$translatedSubject = (string)$l10n->t('You\'ve received a new notification in %s : "%s"', [$serverUrl, $generatedId]);
		$emailMessage->setSubject($translatedSubject);

		$parsedSubject = $notification->getParsedSubject();
		$parsedMessage = $notification->getParsedMessage();

		$htmlText = $this->getMailBody($parsedSubject, $parsedMessage, $serverUrl, 'mail/htmlmail');
		$plainText = $this->getMailBody($parsedSubject, $parsedMessage, $serverUrl, 'mail/plaintextmail');

		$emailMessage->setPlainBody($plainText);
		$emailMessage->setHtmlBody($htmlText);

		$failedRecipents = $this->mailer->send($emailMessage);
		if (!empty($failedRecipents)) {
			// throw a plain exception to converge the mailer->send behaviour
			throw new \Exception('Failed to send mail to ' . implode(', ', $failedRecipents));
		}

		return $emailMessage;
	}

	/**
	 * This function just exposes the IMailer::validateMailAddress method
	 * @param string $email the email to be validated
	 * @return bool true if the email is valid, false otherwise
	 */
	public function validateEmail($email) {
		return $this->mailer->validateMailAddress($email);
	}

	/**
	 * Check if the notification will be sent according to the configuration set. This will be checked
	 * here to enforce the behaviour, but it should be also checked upwards to fail faster.
	 * The checks of this function shouldn't consider the notification as prepared in order to use
	 * this function as soon as possible
	 * @param INotification $notification the notification that will be checked
	 * @return true if the notification will be sent by the sendNotification method, false otherwise
	 */
	public function willSendNotification(INotification $notification) {
		$options = $this->optionsStorage->getOptions($notification->getUser());
		$option = $options['email_sending_option'];
		switch ($option) {
			case 'never':
				return false;
			case 'always':
				return true;
			case 'action':
				return !empty($notification->getActions());
			default:
				return false;
		}
	}

	private function getMailBody($subject, $message, $serverUrl, $targetTemplate) {
		$tmpl = new Template('notifications', $targetTemplate, '', false);
		$tmpl->assign('subject', $subject);
		$tmpl->assign('message', $message);
		$tmpl->assign('serverUrl', $serverUrl);
		return $tmpl->fetchPage();
	}
}
