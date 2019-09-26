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
namespace OCA\Notifications\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\OCSResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Notification\IAction;
use OCA\Notifications\Handler;

class EndpointV2Controller extends Controller {
	/** lists will return a maximum number of 20 results */
	const ENFORCED_LIST_LIMIT = 20;

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

	public function __construct(Handler $handler, IManager $manager, IUserSession $userSession, IConfig $config, IURLGenerator $urlGenerator, IRequest $request) {
		parent::__construct('notifications', $request);
		$this->handler = $handler;
		$this->manager = $manager;
		$this->userSession = $userSession;
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $id the id of the notification
	 * @param string $fetch the fetch order
	 * @param int $limit the limit for the number of notifications to be returned
	 * @param string $format the format of the result: 'json' or 'xml'
	 */
	public function listNotifications($id = null, $fetch = 'desc', $limit = self::ENFORCED_LIST_LIMIT, $format = 'json') {
		$userObject = $this->userSession->getUser();
		if ($userObject === null) {
			$ocsResponse = new OCSResponse($format, Http::STATUS_FORBIDDEN, null, [], null, null, true);
			$ocsResponse->setStatus(Http::STATUS_FORBIDDEN);
			return $ocsResponse;
		}
		$userid = $userObject->getUID();

		// if it's "asc" keep it, if not consider it "desc"
		if (strtoupper($fetch) !== 'ASC') {
			$order = 'DESC';
		} else {
			$order = 'ASC';
		}

		$maxResults = min(self::ENFORCED_LIST_LIMIT, $limit);

		$language = $this->config->getUserValue($userid, 'core', 'lang', null);

		// fetch the max id before getting the list of notifications
		$maxId = $this->handler->getMaxNotificationId($userid);
		if ($maxId === null) {
			$maxId = -1;
		}

		if ($order === 'ASC') {
			// try to get an additional result to check if there is more results available or not
			$notifications = $this->handler->fetchAscendentList($userid, $id, $maxResults + 1, function (INotification $rawNotification) use ($language) {
				// we need to prepare the notification in order to decide to discard it or not.
				// we'll return the prepared notification or null in case of exceptions.
				return $this->prepareNotification($rawNotification, $language);
			});
		} else {
			$notifications = $this->handler->fetchDescendentList($userid, $id, $maxResults + 1, function (INotification $rawNotification) use ($language) {
				return $this->prepareNotification($rawNotification, $language);
			});
		}

		$data = [];
		foreach ($notifications as $notificationId => $notification) {
			$data[] = $this->notificationToArray($notificationId, $notification);
		}

		if (count($data) > $maxResults) {
			// make sure we return the number of results specified
			$data = array_slice($data, 0, $maxResults, true);

			$url = $this->urlGenerator->linkToRoute('notifications.EndpointV2.listNotifications', [
				'id' => $data[count($data) - 1]['notification_id'],
				'fetch' => strtolower($order),
				'limit' => $maxResults,
				'format' => $format,
			]);

			$ocsResponse = new OCSResponse($format, Http::STATUS_OK, null, [
				'notifications' => $data,
				'next' => $url,
			], null, null, true);
		} else {
			$ocsResponse = new OCSResponse($format, Http::STATUS_OK, null, [
				'notifications' => $data,
			], null, null, true);
		}
		$ocsResponse->addHeader('OC-Last-Notification', $maxId);
		return $ocsResponse;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $id
	 * @param string $format either 'json' or 'xml'
	 * @return Result
	 */
	public function getNotification($id, $format = 'json') {
		$userObject = $this->userSession->getUser();
		if ($userObject === null) {
			$ocsResponse = new OCSResponse($format, Http::STATUS_FORBIDDEN, null, [], null, null, true);
			$ocsResponse->setStatus(Http::STATUS_FORBIDDEN);
			return $ocsResponse;
		}
		$userid = $userObject->getUID();

		$notification = $this->handler->getById($id, $userid);

		if (!($notification instanceof INotification)) {
			$ocsResponse = new OCSResponse($format, Http::STATUS_NOT_FOUND, null, [], null, null, true);
			$ocsResponse->setStatus(Http::STATUS_NOT_FOUND);
			return $ocsResponse;
		}

		$language = $this->config->getUserValue($userid, 'core', 'lang', null);

		try {
			$notification = $this->manager->prepare($notification, $language);
		} catch (\InvalidArgumentException $e) {
			// The app was disabled
			$ocsResponse = new OCSResponse($format, Http::STATUS_NOT_FOUND, null, [], null, null, true);
			$ocsResponse->setStatus(Http::STATUS_NOT_FOUND);
			return $ocsResponse;
		}

		return new OCSResponse($format, Http::STATUS_OK, null,
			$this->notificationToArray($id, $notification), null, null, true);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $id
	 * @param string $format either 'json' or 'xml'
	 * @return Result
	 */
	public function deleteNotification($id, $format = 'json') {
		$userObject = $this->userSession->getUser();
		if ($userObject === null) {
			$ocsResponse = new OCSResponse($format, Http::STATUS_FORBIDDEN, null, [], null, null, true);
			$ocsResponse->setStatus(Http::STATUS_FORBIDDEN);
			return $ocsResponse;
		}
		$userid = $userObject->getUID();

		$this->handler->deleteById($id, $userid);
		return new OCSResponse($format, Http::STATUS_OK, null, [], null, null, true);
	}


	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired

	 * @param string $format either 'json' or 'xml'
	 */
	public function getLastNotificationId($format = 'json') {
		$userObject = $this->userSession->getUser();
		if ($userObject === null) {
			$ocsResponse = new OCSResponse($format, Http::STATUS_FORBIDDEN, null, [], null, null, true);
			$ocsResponse->setStatus(Http::STATUS_FORBIDDEN);
			return $ocsResponse;
		}
		$userid = $userObject->getUID();


		$maxId = $this->handler->getMaxNotificationId($userid);
		if ($maxId === null) {
			$maxId = -1;
		}

		$ocsResponse = new OCSResponse($format, Http::STATUS_OK, null, ['id' => $maxId], null, null, true);
		$ocsResponse->addHeader('OC-Last-Notification', $maxId);
		return $ocsResponse;
	}

	/**
	 * @param int $notificationId
	 * @param INotification $notification
	 * @return array
	 */
	protected function notificationToArray($notificationId, INotification $notification) {
		$data = [
			'notification_id' => $notificationId,
			'app' => $notification->getApp(),
			'user' => $notification->getUser(),
			'datetime' => $notification->getDateTime()->format('c'),
			'object_type' => $notification->getObjectType(),
			'object_id' => $notification->getObjectId(),
			'subject' => $notification->getParsedSubject(),
			'message' => $notification->getParsedMessage(),
			'link' => $notification->getLink(),
			'actions' => [],
		];
		if (method_exists($notification, 'getIcon')) {
			$data['icon'] = $notification->getIcon();
		}

		foreach ($notification->getParsedActions() as $action) {
			$data['actions'][] = $this->actionToArray($action);
		}

		return $data;
	}

	/**
	 * @param IAction $action
	 * @return array
	 */
	protected function actionToArray(IAction $action) {
		return [
			'label' => $action->getParsedLabel(),
			'link' => $action->getLink(),
			'type' => $action->getRequestType(),
			'primary' => $action->isPrimary(),
		];
	}

	/**
	 * @internal
	 * Prepare a notification. This should only be used internally as part of a callback in this
	 * class. Use IManager::prepare to prepare the notification.
	 * @param INotification $notification the notification to be prepared
	 * @param string $language the language to be used
	 * @return INotification|null the prepared notification or null if it couldn't be prepared
	 */
	public function prepareNotification(INotification $notification, $language) {
		try {
			return $this->manager->prepare($notification, $language);
		} catch (\InvalidArgumentException $e) {
			// The app was disabled, skip the notification
			return null;
		}
	}
}
