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
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use TestHelpers\SetupHelper;

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
	 *
	 * @return void
	 */
	public function hasBeenSentANotification($user) {
		$this->featureContext->userSendsToOcsApiEndpoint(
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
	 * @When /^the administrator is sent (?:a|another) notification$/
	 * @Given /^the administrator has been sent (?:a|another) notification$/
	 *
	 * @return void
	 */
	public function theAdminHasBeenSentANotification() {
		$this->hasBeenSentANotification(
			$this->featureContext->getAdminUsername()
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
		for ($rowCount = 0; $rowCount < \count($rows); $rowCount ++) {
			$rows[$rowCount] = $this->featureContext->substituteInLineCodes(
				$rows[$rowCount]
			);
		}
		$formData = new TableNode($rows);
		
		$this->featureContext->userSendsHTTPMethodToOcsApiEndpointWithBody(
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
	 * @When /^the administrator is sent (?:a|another) notification with$/
	 * @Given /^the administrator has been sent (?:a|another) notification with$/
	 *
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 *
	 * @return void
	 */
	public function theAdminHasBeenSentANotificationWith(TableNode $formData) {
		$this->hasBeenSentANotificationWith(
			$this->featureContext->getAdminUsername(),
			$formData
		);
	}

	/**
	 * disable CSRF
	 *
	 * @throws Exception
	 * @return string the previous setting of csrf.disabled
	 */
	private function disableCSRF() {
		return $this->setCSRFDotDisabled('true');
	}

	/**
	 * set csrf.disabled
	 *
	 * @param string $setting "true", "false" or "" to delete the setting
	 *
	 * @throws Exception
	 * @return string the previous setting of csrf.disabled
	 */
	private function setCSRFDotDisabled($setting) {
		$oldCSRFSetting = SetupHelper::runOcc(
			['config:system:get', 'csrf.disabled']
		)['stdOut'];

		if ($setting === "") {
			SetupHelper::runOcc(['config:system:delete', 'csrf.disabled']);
		} elseif ($setting !== null) {
			SetupHelper::runOcc(
				[
					'config:system:set',
					'csrf.disabled',
					'--type',
					'boolean',
					'--value',
					$setting
				]
			);
		}
		return \trim($oldCSRFSetting);
	}

	/**
	 * @When the user :user sets the email notification option to :setting using the API
	 *
	 * @param string $user
	 * @param string $setting
	 *
	 * @throws Exception
	 * @return void
	 */
	public function setEmailNotificationOption($user, $setting) {
		$oldCSRFSetting = $this->disableCSRF();

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

		$this->setCSRFDotDisabled($oldCSRFSetting);

		PHPUnit_Framework_Assert::assertEquals(
			200, $response->getStatusCode(),
			"could not set notification option " . $response->getReasonPhrase()
		);
		$responseDecoded = \json_decode($response->getBody());
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
	 * @When the administrator sets the email notification option to :setting using the API
	 *
	 * @param string $setting
	 *
	 * @throws Exception
	 * @return void
	 */
	public function theAdminSetsEmailNotificationOption($setting) {
		$this->setEmailNotificationOption(
			$this->featureContext->getAdminUsername(),
			$setting
		);
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
		PHPUnit_Framework_Assert::assertNotEmpty(
			$this->notificationsCoreContext->getNotificationIds()
		);
		$lastNotificationIds
			= $this->notificationsCoreContext->getLastNotificationIds();
		if ($firstOrLast === 'first') {
			$this->notificationsCoreContext->setDeletedNotification(
				\end($lastNotificationIds)
			);
		} else {
			$this->notificationsCoreContext->setDeletedNotification(
				\reset($lastNotificationIds)
			);
		}
		$this->featureContext->userSendsToOcsApiEndpoint(
			$user,
			'DELETE',
			'/apps/notifications/api/v1/notifications/'
			. $this->notificationsCoreContext->getDeletedNotification()
		);
	}

	/**
	 * @When /^the administrator deletes the (first|last) notification$/
	 *
	 * @param string $firstOrLast
	 *
	 * @return void
	 */
	public function theAdminDeletesNotification($firstOrLast) {
		$this->deleteNotification(
			$this->featureContext->getAdminUsername(),
			$firstOrLast
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
		$this->notificationsCoreContext = $environment->getContext(
			'NotificationsCoreContext'
		);
		SetupHelper::init(
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getOcPath()
		);
	}
}
