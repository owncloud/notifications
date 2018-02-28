<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use GuzzleHttp\Client;
use GuzzleHttp\Message\ResponseInterface;

require __DIR__ . '/../../vendor/autoload.php';
require_once 'bootstrap.php';

/**
 * Defines application features from the specific context.
 */
class NotificationsContext implements Context, SnippetAcceptingContext {
	use BasicStructure;

	/** @var array[] */
	protected $notificationIds;

	/** @var int */
	protected $deletedNotification;

	/**
	 * @When /^user "([^"]*)" is sent (?:a|another) notification$/
	 * @Given /^user "([^"]*)" has been sent (?:a|another) notification$/
	 *
	 * @param string $user
	 */
	public function hasBeenSentANotification($user) {
		if ($user === 'test1') {
			$response = $this->setTestingValue('POST', 'apps/notificationsacceptancetesting/notifications', null);
			PHPUnit_Framework_Assert::assertEquals(200, $response->getStatusCode());
			PHPUnit_Framework_Assert::assertEquals(200, (int) $this->getOCSResponseStatusCode($response));
		}
	}

	/**
	 * @When /^user "([^"]*)" is sent (?:a|another) notification with$/
	 * @Given /^user "([^"]*)" has been sent (?:a|another) notification with$/
	 *
	 * @param string $user
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 */
	public function hasBeenSentANotificationWith($user, \Behat\Gherkin\Node\TableNode $formData) {
		if ($user === 'test1') {
			$response = $this->setTestingValue('POST', 'apps/notificationsacceptancetesting/notifications', $formData);
			PHPUnit_Framework_Assert::assertEquals(200, $response->getStatusCode());
			PHPUnit_Framework_Assert::assertEquals(200, (int) $this->getOCSResponseStatusCode($response));
		}
	}

	/**
	 * @Then /^the list of notifications should have (\d+) (?:entry|entries)$/
	 *
	 * @param int $numNotifications
	 */
	public function checkNumNotifications($numNotifications) {
		$notifications = $this->getArrayOfNotificationsResponded($this->response);
		PHPUnit_Framework_Assert::assertCount((int) $numNotifications, $notifications);

		$notificationIds = [];
		foreach ($notifications as $notification) {
			$notificationIds[] = (int) $notification['notification_id'];
		}

		$this->notificationIds[] = $notificationIds;
	}

	/**
	 * @Then /^user "([^"]*)" should have (\d+) notification(?:s|)(| missing the last one| missing the first one)$/
	 *
	 * @param string $user
	 * @param int $numNotifications
	 * @param string $missingLast
	 */
	public function userNumNotifications($user, $numNotifications, $missingLast) {
		if ($user === 'test1') {
			$this->sendingTo('GET', '/apps/notifications/api/v1/notifications?format=json');
			PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());

			$previousNotificationIds = [];
			if ($missingLast) {
				PHPUnit_Framework_Assert::assertNotEmpty($this->notificationIds);
				$previousNotificationIds = end($this->notificationIds);
			}

			$this->checkNumNotifications((int) $numNotifications);

			if ($missingLast) {
				$now = end($this->notificationIds);
				if ($missingLast === ' missing the last one') {
					array_unshift($now, $this->deletedNotification);
				} else {
					$now[] = $this->deletedNotification;
				}

				PHPUnit_Framework_Assert::assertEquals($previousNotificationIds, $now);
			}

		}
	}

	/**
	 * @Then /^the (first|last) notification should match$/
	 *
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 */
	public function matchNotification($notification, $formData) {
		$lastNotifications = end($this->notificationIds);
		if ($notification === 'first') {
			$notificationId = reset($lastNotifications);
		} else/* if ($notification === 'last')*/ {
			$notificationId = end($lastNotifications);
		}

		$this->sendingTo('GET', '/apps/notifications/api/v1/notifications/' . $notificationId . '?format=json');
		PHPUnit_Framework_Assert::assertEquals(200, $this->response->getStatusCode());
		$response = json_decode($this->response->getBody()->getContents(), true);

		foreach ($formData->getRowsHash() as $key => $value) {
			PHPUnit_Framework_Assert::assertArrayHasKey($key, $response['ocs']['data']);
			PHPUnit_Framework_Assert::assertEquals($value, $response['ocs']['data'][$key]);
		}
	}

	/**
	 * @When /^the user deletes the (first|last) notification$/
	 *
	 * @param string $firstOrLast
	 */
	public function deleteNotification($firstOrLast) {
		PHPUnit_Framework_Assert::assertNotEmpty($this->notificationIds);
		$lastNotificationIds = end($this->notificationIds);
		if ($firstOrLast === 'first') {
			$this->deletedNotification = end($lastNotificationIds);
		} else {
			$this->deletedNotification = reset($lastNotificationIds);
		}
		$this->sendingTo('DELETE', '/apps/notifications/api/v1/notifications/' . $this->deletedNotification);
	}

	/**
	 * Parses the xml answer to get the array of users returned.
	 * @param ResponseInterface $resp
	 * @return array
	 */
	public function getArrayOfNotificationsResponded(ResponseInterface $resp) {
		$jsonResponse = json_decode($resp->getBody()->getContents(), 1);
		return $jsonResponse['ocs']['data'];
	}

	/**
	 * @BeforeScenario
	 * @AfterScenario
	 */
	public function clearNotifications() {
		$response = $this->setTestingValue('DELETE', 'apps/notificationsacceptancetesting', null);
		PHPUnit_Framework_Assert::assertEquals(200, $response->getStatusCode());
		PHPUnit_Framework_Assert::assertEquals(200, (int) $this->getOCSResponseStatusCode($response));
	}

	/**
	 * @param $verb
	 * @param $url
	 * @param $body
	 * @return \GuzzleHttp\Message\FutureResponse|ResponseInterface|null
	 */
	protected function setTestingValue($verb, $url, $body) {
		$fullUrl = $this->baseUrl . 'v2.php/' . $url;
		$client = new Client();
		$options = [
			'auth' => ['admin', 'admin'],
		];
		if ($body instanceof \Behat\Gherkin\Node\TableNode) {
			$fd = $body->getRowsHash();
			$options['body'] = $fd;
		}

		try {
			return $client->send($client->createRequest($verb, $fullUrl, $options));
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			return $ex->getResponse();
		}
	}

	/**
	 * Abstract method implemented from Core's FeatureContext
	 */
	protected function resetAppConfigs() {}
}
