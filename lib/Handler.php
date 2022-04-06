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

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Notification\IManager;
use OCP\Notification\INotification;

class Handler {
	/**
	 * Used in fetchDescendentList and fetchAscendentList method to limit the number of results
	 * Intended to be used as default value, but a larger value can be set (no enforcement)
	 */
	const FETCH_DEFAULT_LIMIT = 20;

	/** @var IDBConnection */
	protected $connection;

	/** @var IManager */
	protected $manager;

	/**
	 * @param IDBConnection $connection
	 * @param IManager $manager
	 */
	public function __construct(IDBConnection $connection, IManager $manager) {
		$this->connection = $connection;
		$this->manager = $manager;
	}

	/**
	 * Add a new notification to the database
	 *
	 * @param INotification $notification
	 */
	public function add(INotification $notification) {
		$sql = $this->connection->getQueryBuilder();
		$sql->insert('notifications');
		$this->sqlInsert($sql, $notification);
		$sql->execute();
	}

	/**
	 * Count the notifications matching the given Notification
	 *
	 * @param INotification $notification
	 * @return int
	 */
	public function count(INotification $notification) {
		$sql = $this->connection->getQueryBuilder();
		$sql->select($sql->createFunction('COUNT(*)'))
			->from('notifications');

		$this->sqlWhere($sql, $notification);

		$statement = $sql->execute();
		/* @phan-suppress-next-line PhanDeprecatedFunction */
		$count = (int) $statement->fetchColumn();
		/* @phan-suppress-next-line PhanDeprecatedFunction */
		$statement->closeCursor();

		return $count;
	}

	/**
	 * Delete the notifications matching the given Notification
	 *
	 * @param INotification $notification
	 */
	public function delete(INotification $notification) {
		$sql = $this->connection->getQueryBuilder();
		$sql->delete('notifications');
		$this->sqlWhere($sql, $notification);
		$sql->execute();
	}

	/**
	 * This method deletes the notifications of the user after the user is deleted
	 * from the notifications table.
	 *
	 * @param string $uid uid of the user
	 */
	public function deleteUserNotifications($uid) {
		$sql = $this->connection->getQueryBuilder();
		$sql->delete('notifications')
			->where($sql->expr()->eq('user', $sql->createNamedParameter($uid)));
		$sql->execute();
	}

	/**
	 * Delete the notification matching the given id
	 *
	 * @param int $id
	 * @param string $user
	 */
	public function deleteById($id, $user) {
		$sql = $this->connection->getQueryBuilder();
		$sql->delete('notifications')
			->where($sql->expr()->eq('notification_id', $sql->createParameter('id')))
			->setParameter('id', $id)
			->andWhere($sql->expr()->eq('user', $sql->createParameter('user')))
			->setParameter('user', $user);
		$sql->execute();
	}

	/**
	 * Get the notification matching the given id
	 *
	 * @param int $id
	 * @param string $user
	 * @return null|INotification
	 */
	public function getById($id, $user) {
		$sql = $this->connection->getQueryBuilder();
		$sql->select('*')
			->from('notifications')
			->where($sql->expr()->eq('notification_id', $sql->createParameter('id')))
			->setParameter('id', $id)
			->andWhere($sql->expr()->eq('user', $sql->createParameter('user')))
			->setParameter('user', $user);
		$statement = $sql->execute();

		$notification = null;
		/* @phan-suppress-next-line PhanDeprecatedFunction */
		if ($row = $statement->fetch()) {
			$notification = $this->notificationFromRow($row);
		}
		/* @phan-suppress-next-line PhanDeprecatedFunction */
		$statement->closeCursor();

		return $notification;
	}

	/**
	 * Return the notifications matching the given Notification
	 *
	 * @param INotification $notification
	 * @param int $limit
	 * @return array [notification_id => INotification]
	 */
	public function get(INotification $notification, $limit = 25) {
		$sql = $this->connection->getQueryBuilder();
		$sql->select('*')
			->from('notifications')
			->orderBy('notification_id', 'DESC')
			->setMaxResults($limit);

		$this->sqlWhere($sql, $notification);
		$statement = $sql->execute();

		$notifications = [];
		/* @phan-suppress-next-line PhanDeprecatedFunction */
		while ($row = $statement->fetch()) {
			$notifications[(int) $row['notification_id']] = $this->notificationFromRow($row);
		}
		/* @phan-suppress-next-line PhanDeprecatedFunction */
		$statement->closeCursor();

		return $notifications;
	}

	/**
	 * Fetch a list of notifications starting from notification id $id up to $limit number of
	 * notifications. If id = null, we'll start from the newest id available. If there is a
	 * notification matching the id, it will be ignored and won't be returned
	 * @param string $user the target user for the notifications
	 * @param int|null $id the starting notification id (it won't be returned)
	 * @param int $limit the maximum number of notifications that will be fetched
	 * @param callable|null $callable the condition that the notification should match to be part
	 * of the result. The callable will be called with a INotification object as parameter such as
	 * "$callable(INotification $notification)" and must return an INotification object if such
	 * notification should be part of the result or null otherwise
	 * @return array [notification_id => INotification]
	 */
	public function fetchDescendentList($user, $id = null, $limit = self::FETCH_DEFAULT_LIMIT, $callable = null) {
		$sql = $this->connection->getQueryBuilder();
		$sql->select('*')
			->from('notifications')
			->orderBy('notification_id', 'DESC');

		$sql->where($sql->expr()->eq('user', $sql->createNamedParameter($user)));
		if ($id !== null) {
			$sql->andWhere($sql->expr()->lt('notification_id', $sql->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		}

		$statement = $sql->execute();

		$notifications = [];
		$numberOfNotifications = 0;
		while (($row = $statement->fetch()) && $numberOfNotifications < $limit) {
			$notification = $this->notificationFromRow($row);
			$processedNotification = $this->processByCallback($notification, $callable);
			if ($processedNotification !== null) {
				$notifications[(int) $row['notification_id']] = $processedNotification;
				$numberOfNotifications++;
			}
		}
		$statement->closeCursor();

		return $notifications;
	}

	/**
	 * Fetch a list of notifications starting from notification id $id up to $limit number of
	 * notifications. If id = null, we'll start from the oldest id available. If there is a
	 * notification matching the id, it will be ignored and won't be returned
	 * @param string $user the target user for the notifications
	 * @param int|null $id the starting notification id (it won't be returned)
	 * @param int $limit the maximum number of notifications that will be fetched
	 * @param callable|null $callable the condition that the notification should match to be part
	 * of the result. The callable will be called with a INotification object as parameter such as
	 * "$callable(INotification $notification)" and must return an INotification object if such
	 * notification should be part of the result or null otherwise
	 * @return array [notification_id => INotification]
	 */
	public function fetchAscendentList($user, $id = null, $limit = self::FETCH_DEFAULT_LIMIT, $callable = null) {
		$sql = $this->connection->getQueryBuilder();
		$sql->select('*')
			->from('notifications')
			->orderBy('notification_id', 'ASC');

		$sql->where($sql->expr()->eq('user', $sql->createNamedParameter($user)));
		if ($id !== null) {
			$sql->andWhere($sql->expr()->gt('notification_id', $sql->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		}

		$statement = $sql->execute();

		$notifications = [];
		$numberOfNotifications = 0;
		while (($row = $statement->fetch()) && $numberOfNotifications < $limit) {
			$notification = $this->notificationFromRow($row);
			$processedNotification = $this->processByCallback($notification, $callable);
			if ($processedNotification !== null) {
				$notifications[(int) $row['notification_id']] = $processedNotification;
				$numberOfNotifications++;
			}
		}
		$statement->closeCursor();

		return $notifications;
	}

	/**
	 * Get the maximum notification id available for the user
	 * @param string $user the user to be filtered with
	 * @return int|null the maximum notification id for the user or null if the user doesn't have
	 * any notification
	 */
	public function getMaxNotificationId($user) {
		$sql = $this->connection->getQueryBuilder();
		$sql->select($sql->createFunction('max(`notification_id`) as `max_id`'))
			->from('notifications')
			->where($sql->expr()->eq('user', $sql->createNamedParameter($user)));

		$statement = $sql->execute();
		$row = $statement->fetch();
		$maxId = $row['max_id'];
		$statement->closeCursor();

		return $maxId;
	}

	private function processByCallback(INotification $notification, $callable = null) {
		if (is_callable($callable)) {
			return $callable($notification);
		} else {
			return $notification;
		}
	}

	/**
	 * Add where statements to a query builder matching the given notification
	 *
	 * @param IQueryBuilder $sql
	 * @param INotification $notification
	 */
	protected function sqlWhere(IQueryBuilder $sql, INotification $notification) {
		if ($notification->getApp() !== '') {
			$sql->andWhere($sql->expr()->eq('app', $sql->createParameter('app')));
			$sql->setParameter('app', $notification->getApp());
		}

		if ($notification->getUser() !== '') {
			$sql->andWhere($sql->expr()->eq('user', $sql->createParameter('user')))
				->setParameter('user', $notification->getUser());
		}

		if ($notification->getDateTime()->getTimestamp() !== 0) {
			$sql->andWhere($sql->expr()->eq('timestamp', $sql->createParameter('timestamp')))
				->setParameter('timestamp', $notification->getDateTime()->getTimestamp());
		}

		if ($notification->getObjectType() !== '') {
			$sql->andWhere($sql->expr()->eq('object_type', $sql->createParameter('objectType')))
				->setParameter('objectType', $notification->getObjectType());
		}

		if ($notification->getObjectId() !== '') {
			$sql->andWhere($sql->expr()->eq('object_id', $sql->createParameter('objectId')))
				->setParameter('objectId', $notification->getObjectId());
		}

		if ($notification->getSubject() !== '') {
			$sql->andWhere($sql->expr()->eq('subject', $sql->createParameter('subject')))
				->setParameter('subject', $notification->getSubject());
		}

		if ($notification->getMessage() !== '') {
			$sql->andWhere($sql->expr()->eq('message', $sql->createParameter('message')))
				->setParameter('message', $notification->getMessage());
		}

		if ($notification->getLink() !== '') {
			$sql->andWhere($sql->expr()->eq('link', $sql->createParameter('link')))
				->setParameter('link', $notification->getLink());
		}
	}

	/**
	 * Turn a notification into an input statement
	 *
	 * @param IQueryBuilder $sql
	 * @param INotification $notification
	 */
	protected function sqlInsert(IQueryBuilder $sql, INotification $notification) {
		$sql->setValue('app', $sql->createParameter('app'))
			->setParameter('app', $notification->getApp());

		$sql->setValue('user', $sql->createParameter('user'))
			->setParameter('user', $notification->getUser());

		$sql->setValue('timestamp', $sql->createParameter('timestamp'))
			->setParameter('timestamp', $notification->getDateTime()->getTimestamp());

		$sql->setValue('object_type', $sql->createParameter('objectType'))
			->setParameter('objectType', $notification->getObjectType());

		$sql->setValue('object_id', $sql->createParameter('objectId'))
			->setParameter('objectId', $notification->getObjectId());

		$sql->setValue('subject', $sql->createParameter('subject'))
			->setParameter('subject', $notification->getSubject());

		$sql->setValue('subject_parameters', $sql->createParameter('subject_parameters'))
			->setParameter('subject_parameters', \json_encode($notification->getSubjectParameters()));

		$sql->setValue('message', $sql->createParameter('message'))
			->setParameter('message', $notification->getMessage());

		$sql->setValue('message_parameters', $sql->createParameter('message_parameters'))
			->setParameter('message_parameters', \json_encode($notification->getMessageParameters()));

		$sql->setValue('link', $sql->createParameter('link'))
			->setParameter('link', $notification->getLink());

		if (\method_exists($notification, 'getIcon')) {
			$sql->setValue('icon', $sql->createNamedParameter($notification->getIcon()));
		}

		$actions = [];
		foreach ($notification->getActions() as $action) {
			/** @var \OCP\Notification\IAction $action */
			$actions[] = [
				'label' => $action->getLabel(),
				'link' => $action->getLink(),
				'type' => $action->getRequestType(),
				'primary' => $action->isPrimary(),
			];
		}
		$sql->setValue('actions', $sql->createParameter('actions'))
			->setParameter('actions', \json_encode($actions));
	}

	/**
	 * Turn a database row into a INotification
	 *
	 * @param array $row
	 * @return INotification
	 */
	protected function notificationFromRow(array $row) {
		$dateTime = new \DateTime();
		$dateTime->setTimestamp((int) $row['timestamp']);

		$notification = $this->manager->createNotification();
		$notification->setApp($row['app'])
			->setUser($row['user'])
			->setDateTime($dateTime)
			->setObject($row['object_type'], $row['object_id'])
			->setSubject($row['subject'], (array) \json_decode($row['subject_parameters'], true));

		if ($row['message'] !== '' && $row['message'] !== null) {
			$notification->setMessage($row['message'], (array) \json_decode($row['message_parameters'], true));
		}
		if ($row['link'] !== '' && $row['link'] !== null) {
			$notification->setLink($row['link']);
		}
		if (\method_exists($notification, 'setIcon') && isset($row['icon']) && $row['icon'] !== '' && $row['icon'] !== null) {
			$notification->setIcon($row['icon']);
		}

		$actions = (array) \json_decode($row['actions'], true);
		foreach ($actions as $actionData) {
			$action = $notification->createAction();
			$action->setLabel($actionData['label'])
				->setLink($actionData['link'], $actionData['type']);
			if (isset($actionData['primary'])) {
				$action->setPrimary($actionData['primary']);
			}
			$notification->addAction($action);
		}

		return $notification;
	}

	/**
	 * Remove the base url from absolute links in the database.
	 * This affects the columns 'link' and 'action'.
	 * e.g: http://owncloud.com/test -> /test
	 *
	 * @return int number of updated notifications
	 */
	public function removeBaseUrlFromAbsoluteLinks() {
		$sql = $this->connection->getQueryBuilder();
		$sql->select(['notification_id', 'link', 'actions'])
			->from('notifications')
			->where($sql->expr()->like('link', $sql->createPositionalParameter('http%')))
			->orWhere($sql->expr()->like('actions', $sql->createPositionalParameter('%"link":"http%')));

		$statement = $sql->execute();
		$counter = 0;

		/* @phan-suppress-next-line PhanDeprecatedFunction */
		while ($row = $statement->fetch()) {
			$sql = $this->connection->getQueryBuilder();
			$sql->update('notifications')
				->where($sql->expr()->eq('notification_id', $sql->createNamedParameter($row['notification_id'])));

			$linkUrlComponents = \parse_url($row['link']);
			if (isset($linkUrlComponents['scheme'], $linkUrlComponents['path'])) {
				$sql->set('link', $sql->createNamedParameter($linkUrlComponents['path']));
			}

			if (\strpos($row['actions'], 'http') !== false) {
				$actions = \json_decode($row['actions'], true);

				foreach ($actions as $index => $action) {
					$actionUrlComponents = \parse_url($action['link']);
					if (isset($actionUrlComponents['scheme'], $actionUrlComponents['path'])) {
						$actions[$index]['link'] = $actionUrlComponents['path'];
					}
				}

				$sql->set('actions', $sql->createNamedParameter(\json_encode($actions)));
			}

			$counter++;
			$sql->execute();
		}

		/* @phan-suppress-next-line PhanDeprecatedFunction */
		$statement->closeCursor();

		return $counter;
	}
}
