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
class NotificationsContext implements Context {

	/**
	 * @var FeatureContext
	 */
	private $featureContext;

	/**
	 * @var NotificationsCoreContext
	 */
	private $notificationsCoreContext;

	/**
	 * @When /^user "([^"]*)" is sent (?:a|another) notification$/
	 * @Given /^user "([^"]*)" has been sent (?:a|another) notification$/
	 *
	 * @param string $user
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
	 */
	public function hasBeenSentANotificationWith($user, TableNode $formData) {
		//add username to the TableNode,
		//so it does not need to be mentioned in the table
		$rows = $formData->getRows();
		$rows[] = ["user", $user];
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
	 * @When /^user "([^"]*)" deletes the (first|last) notification$/
	 *
	 * @param string $user
	 * @param string $firstOrLast
	 */
	public function deleteNotification($user, $firstOrLast) {
		PHPUnit_Framework_Assert::assertNotEmpty(
			$this->notificationsCoreContext->getNotificationIds()
		);
		$lastNotificationIds = $this->notificationsCoreContext->getLastNotificationIds();
		if ($firstOrLast === 'first') {
			$this->notificationsCoreContext->setDeletedNotification(
				end($lastNotificationIds)
			);
		} else {
			$this->notificationsCoreContext->setDeletedNotification(
				reset($lastNotificationIds)
			);
		}
		$this->featureContext->userSendingTo(
			$user,
			'DELETE',
			'/apps/notifications/api/v1/notifications/' . $this->notificationsCoreContext->getDeletedNotification()
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
		$this->notificationsCoreContext = $environment->getContext('NotificationsCoreContext');
	}

	/**
	 * Abstract method implemented from Core's FeatureContext
	 */
	protected function resetAppConfigs() {}
}
