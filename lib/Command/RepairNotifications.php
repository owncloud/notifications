<?php
/**
 * @author Jannik Stehle <jstehle@owncloud.com>
 * @author Jan Ackermann <jackermann@owncloud.com>
 *
 * @copyright Copyright (c) 2021, ownCloud GmbH
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

namespace OCA\Notifications\Command;

use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RepairNotifications extends Command {

	/** @var IDBConnection */
	protected $connection;

	private static $availableSubjects = [
		'relativeLinks'
	];

	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		parent::__construct();
		$this->connection = $connection;
	}

	protected function configure() {
		$this
			->setName('notifications:repairNotifications')
			->setDescription('Repair existing notifications')
			->addArgument('subject', InputArgument::REQUIRED, 'Subject to repair')
		;
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return false|int
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$subject = $input->getArgument('subject');

		if (!\in_array($subject, self::$availableSubjects)) {
			throw new \LogicException('Invalid subject');
		}

		$sql = $this->connection->getQueryBuilder();
		$sql->select(['notification_id', 'link', 'actions'])
			->from('notifications')
			->where($sql->expr()->like('link', $sql->createPositionalParameter('http%')))
			->orWhere($sql->expr()->like('actions', $sql->createPositionalParameter('%"link":"http%')));

		$result = $sql->execute()->fetchAll();

		if (!$result) {
			$output->writeln('No notifications found to repair.');
			return false;
		}

		$output->writeln(\sprintf('%s notification(s) found to repair', \count($result)));

		foreach ($result as $row) {
			$output->writeln(\sprintf('Repairing notification with ID %s...', $row['notification_id']));

			$sql = $this->connection->getQueryBuilder();
			$sql->update('notifications')
				->where($sql->expr()->eq('notification_id', $sql->createNamedParameter($row['notification_id'])));

			$linkUrlComponents = \parse_url($row['link']);
			if (\array_key_exists('scheme', $linkUrlComponents)) {
				$newLink = \parse_url($row['link'], PHP_URL_PATH);
				$sql->set('link', $sql->createNamedParameter($newLink));
			}

			if (\strpos($row['actions'], 'http') !== false) {
				$actions = \json_decode($row['actions'], true);

				foreach ($actions as &$action) {
					$actionUrlComponents = \parse_url($action['link']);
					if (\array_key_exists('scheme', $actionUrlComponents)) {
						$action['link'] = \parse_url($action['link'], PHP_URL_PATH);
					}
				}

				$sql->set('actions', $sql->createNamedParameter(\json_encode($actions)));
			}

			$sql->execute();
		}

		$output->writeln('Done');
		return 0;
	}
}
