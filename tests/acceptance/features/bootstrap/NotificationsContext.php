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
 * You should have received a copy of the GNU Affero General Public License,
 * version 3, along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Message\ResponseInterface;
use TestHelpers\OcsApiHelper;
use GuzzleHttp\Client;
use TestHelpers\EmailHelper;

require_once 'bootstrap.php';

/**
 * Defines application features from the specific context.
 */
class NotificationsContext implements Context, SnippetAcceptingContext {

	/**
	 * @var array[] 
	 */
	protected $notificationIds;

	/**
	 * @var int 
	 */
	protected $deletedNotification;

	/**
	 * @var FeatureContext
	 */
	private $featureContext;

	/**
	 * @When /^user "([^"]*)" is sent (?:a|another) notification$/
	 * @Given /^user "([^"]*)" has been sent (?:a|another) notification$/
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function hasBeenSentANotification($user) {
		$this->featureContext->userSendingTo(
			$user,
			'POST', '/apps/testing/api/v1/notifications'
		);
		$response = $this->featureContext->getResponse();
		PHPUnit_Framework_Assert::assertEquals(200, $response->getStatusCode());
		PHPUnit_Framework_Assert::assertEquals(
			200, (int) $this->featureContext->getOCSResponseStatusCode($response)
		);
	}

	/**
	 * @When /^user "([^"]*)" is sent (?:a|another) notification with$/
	 * @Given /^user "([^"]*)" has been sent (?:a|another) notification with$/
	 *
	 * @param string $user
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 *
	 * @return void
	 */
	public function hasBeenSentANotificationWith($user, TableNode $formData) {
		//add username to the TableNode,
		//so it does not need to be mentioned in the table
		$rows = $formData->getRows();
		$rows[] = ["user", $user];
		for ($rowCount = 0; $rowCount < count($rows); $rowCount ++) {
			$rows[$rowCount] = $this->featureContext->substituteInLineCodes($rows[$rowCount]);
		}
		$formData = new TableNode($rows);
		
		$this->featureContext->userSendsHTTPMethodToAPIEndpointWithBody(
			$this->featureContext->getAdminUsername(),
			'POST', '/apps/testing/api/v1/notifications', $formData
		);
		$response = $this->featureContext->getResponse();
		PHPUnit_Framework_Assert::assertEquals(200, $response->getStatusCode());
		PHPUnit_Framework_Assert::assertEquals(
			200, (int) $this->featureContext->getOCSResponseStatusCode($response)
		);
	}

	/**
	 * @When the user :user sets the email notification option to :setting using the API
	 * 
	 * @param string $user
	 * @param string $setting
	 * 
	 * @return void
	 */
	public function setEmailNotificationOption($user, $setting) {
		$fullUrl = $this->featureContext->getBaseUrl() .
				   "/index.php/apps/notifications/settings/personal/" .
				   "notifications/options";
		$client = new Client();
		$options = [];
		$options['auth'] = [$user, $this->featureContext->getUserPassword($user)];
		$options['headers'] = ['Content-Type' => 'application/json'];
		$options['body'] = '{"email_sending_option":"' . $setting . '"}';
		
		$response = $client->send(
			$client->createRequest("PATCH", $fullUrl, $options)
		);
		PHPUnit_Framework_Assert::assertEquals(
			200, $response->getStatusCode(),
			"could not set notification option " . $response->getReasonPhrase()
		);
		$responseDecoded = json_decode($response->getBody());
		PHPUnit_Framework_Assert::assertEquals(
			$responseDecoded->data->options->id, $user,
			"Could not set notification option! " .
			"'user' in the response is:'" .
			$responseDecoded->data->options->id . "' " .
			"but should be: '$user'"
		);
		PHPUnit_Framework_Assert::assertEquals(
			$responseDecoded->data->options->email_sending_option, $setting,
			"Could not set notification option! " .
			"'email_sending_option' in the response is:'" .
			$responseDecoded->data->options->email_sending_option . "' " .
			"but should be: '$setting'"
		);
	}

	/**
	 * @Then /^the list of notifications should have (\d+) (?:entry|entries)$/
	 *
	 * @param int $numNotifications
	 *
	 * @return void
	 */
	public function checkNumNotifications($numNotifications) {
		$notifications = $this->getArrayOfNotificationsResponded(
			$this->featureContext->getResponse()
		);
		PHPUnit_Framework_Assert::assertCount(
			(int) $numNotifications, $notifications
		);

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
	 *
	 * @return void
	 */
	public function userNumNotifications($user, $numNotifications, $missingLast) {
		$this->featureContext->userSendingTo(
			$user, 'GET', '/apps/notifications/api/v1/notifications?format=json'
		);
		PHPUnit_Framework_Assert::assertEquals(
			200, $this->featureContext->getResponse()->getStatusCode()
		);

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

	/**
	 * @Then /^the (first|last) notification of user "([^"]*)" should match$/
	 *
	 * @param string $notification first|last
	 * @param string $user
	 * @param \Behat\Gherkin\Node\TableNode $formData
	 *
	 * @return void
	 */
	public function matchNotificationPlain(
		$notification, $user, $formData
	) {
		$this->matchNotification(
			$notification, $user, false, $formData
		);
	}

	/**
	 * @Then /^the (first|last) notification of user "([^"]*)" should match these regular expressions$/
	 *
	 * @param string $notification first|last
	 * @param string $user
	 * @param \Behat\Gherkin\Node\TableNode $formData
	 * 
	 * @return void
	 */
	public function matchNotificationRegularExpression(
		$notification, $user, $formData
	) {
		$this->matchNotification(
			$notification, $user, true, $formData
		);
	}

	/**
	 * @param string $notification first|last
	 * @param string $user
	 * @param bool $regex
	 * @param \Behat\Gherkin\Node\TableNode $formData
	 *
	 * @return void
	 */
	public function matchNotification(
		$notification, $user, $regex, $formData
	) {
		$lastNotifications = end($this->notificationIds);
		if ($notification === 'first') {
			$notificationId = reset($lastNotifications);
		} else/* if ($notification === 'last')*/ {
			$notificationId = end($lastNotifications);
		}

		$this->featureContext->userSendingTo(
			$user, 'GET', '/apps/notifications/api/v1/notifications/' .
			$notificationId . '?format=json'
		);
		PHPUnit_Framework_Assert::assertEquals(
			200, $this->featureContext->getResponse()->getStatusCode()
		);
		$response = json_decode(
			$this->featureContext->getResponse()->getBody()->getContents(), true
		);

		foreach ($formData->getRowsHash() as $key => $value) {
			PHPUnit_Framework_Assert::assertArrayHasKey(
				$key, $response['ocs']['data']
			);
			if ($regex) {
				$value = $this->featureContext->substituteInLineCodes(
					$value, ['preg_quote' => ['/'] ]
				);
				PHPUnit_Framework_Assert::assertNotFalse(
					(bool)preg_match($value, $response['ocs']['data'][$key])
				);
			} else {
				$value = $this->featureContext->substituteInLineCodes($value);
				PHPUnit_Framework_Assert::assertEquals(
					$value, $response['ocs']['data'][$key]
				);
			}
		}
	}

	/**
	 * @When /^user "([^"]*)" deletes the (first|last) notification$/
	 *
	 * @param string $user
	 * @param string $firstOrLast
	 *
	 * @return void
	 */
	public function deleteNotification($user, $firstOrLast) {
		PHPUnit_Framework_Assert::assertNotEmpty($this->notificationIds);
		$lastNotificationIds = end($this->notificationIds);
		if ($firstOrLast === 'first') {
			$this->deletedNotification = end($lastNotificationIds);
		} else {
			$this->deletedNotification = reset($lastNotificationIds);
		}
		$this->featureContext->userSendingTo(
			$user,
			'DELETE',
			'/apps/notifications/api/v1/notifications/' . $this->deletedNotification
		);
	}

	/**
	 * Parses the xml answer to get the array of users returned.
	 *
	 * @param ResponseInterface $resp
	 *
	 * @return array
	 */
	public function getArrayOfNotificationsResponded(ResponseInterface $resp) {
		$jsonResponse = json_decode($resp->getBody()->getContents(), 1);
		return $jsonResponse['ocs']['data'];
	}

	/**
	 * 
	 * @AfterScenario
	 *
	 * @return void
	 */
	public function clearNotifications() {
		$response = OcsApiHelper::sendRequest(
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			"DELETE",
			'/apps/testing/api/v1/notifications'
		);
		PHPUnit_Framework_Assert::assertEquals(200, $response->getStatusCode());
		PHPUnit_Framework_Assert::assertEquals(
			200, (int) $this->featureContext->getOCSResponseStatusCode($response)
		);
	}

	/**
	 * @BeforeScenario
	 *
	 * @param BeforeScenarioScope $scope
	 *
	 * @return void
	 */
	public function setUpScenario(BeforeScenarioScope $scope) {
		// Get the environment
		$environment = $scope->getEnvironment();
		// Get all the contexts you need in this context
		$this->featureContext = $environment->getContext('FeatureContext');
		$this->clearNotifications();
	}

	/**
	 * Abstract method implemented from Core's FeatureContext
	 *
	 * @return void
	 */
	protected function resetAppConfigs() {
	}
}
