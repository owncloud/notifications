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
use PHPUnit\Framework\Assert;
use TestHelpers\HttpRequestHelper;
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
	 *
	 * @var OCSContext
	 */
	private $ocsContext;

	/**
	 * @When /^user "([^"]*)" is sent (?:a|another) notification$/
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function userIsSentANotification(string $user):void {
		$bodyTable = new TableNode([['user', $user]]);
		$this->ocsContext->userSendsHTTPMethodToOcsApiEndpointWithBody(
			$user,
			'POST',
			'/apps/testing/api/v1/notifications',
			$bodyTable,
			null
		);
	}

	/**
	 * @Given /^user "([^"]*)" has been sent (?:a|another) notification$/
	 *
	 * @param string $user
	 *
	 * @return void
	 */
	public function userHasBeenSentANotification(string $user):void {
		$this->userIsSentANotification($user);
		$response = $this->featureContext->getResponse();
		Assert::assertEquals(200, $response->getStatusCode());
		Assert::assertEquals(
			200,
			(int) $this->ocsContext->getOCSResponseStatusCode($response)
		);
	}

	/**
	 * @When /^the administrator is sent (?:a|another) notification$/
	 *
	 * @return void
	 */
	public function theAdminIsSentANotification():void {
		$this->userIsSentANotification(
			$this->featureContext->getAdminUsername()
		);
	}

	/**
	 * @Given /^the administrator has been sent (?:a|another) notification$/
	 *
	 * @return void
	 */
	public function theAdminHasBeenSentANotification():void {
		$this->userHasBeenSentANotification(
			$this->featureContext->getAdminUsername()
		);
	}

	/**
	 * @When /^user "([^"]*)" is sent (?:a|another) notification with$/
	 *
	 * @param string $user
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 *
	 * @return void
	 */
	public function userIsSentANotificationWith(string $user, TableNode $formData):void {
		//add username to the TableNode,
		//so it does not need to be mentioned in the table
		$rows = $formData->getRows();
		$rows[] = ["user", $user];
		for ($rowCount = 0; $rowCount < \count($rows); $rowCount ++) {
			$rows[$rowCount][1] = $this->featureContext->substituteInLineCodes(
				$rows[$rowCount][1]
			);
		}
		$formData = new TableNode($rows);

		$this->ocsContext->userSendsHTTPMethodToOcsApiEndpointWithBody(
			$this->featureContext->getAdminUsername(),
			'POST',
			'/apps/testing/api/v1/notifications',
			$formData
		);
	}

	/**
	 * @Given /^user "([^"]*)" has been sent (?:a|another) notification with$/
	 *
	 * @param string $user
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 *
	 * @return void
	 */
	public function userHasBeenSentANotificationWith(string $user, TableNode $formData):void {
		$this->userIsSentANotificationWith($user, $formData);
		$response = $this->featureContext->getResponse();
		Assert::assertEquals(200, $response->getStatusCode());
		Assert::assertEquals(
			200,
			(int) $this->ocsContext->getOCSResponseStatusCode($response)
		);
	}

	/**
	 * @When /^the administrator is sent (?:a|another) notification with$/
	 *
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 *
	 * @return void
	 */
	public function theAdminIsSentANotificationWith(TableNode $formData):void {
		$this->userIsSentANotificationWith(
			$this->featureContext->getAdminUsername(),
			$formData
		);
	}

	/**
	 * @Given /^the administrator has been sent (?:a|another) notification with$/
	 *
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 *
	 * @return void
	 */
	public function theAdminHasBeenSentANotificationWith(TableNode $formData):void {
		$this->userHasBeenSentANotificationWith(
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
	private function disableCSRF():string {
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
	private function setCSRFDotDisabled(string $setting):string {
		$oldCSRFSetting = SetupHelper::runOcc(
			['config:system:get', 'csrf.disabled'],
			$this->featureContext->getStepLineRef()
		)['stdOut'];

		if ($setting === "") {
			SetupHelper::runOcc(
				['config:system:delete', 'csrf.disabled'],
				$this->featureContext->getStepLineRef()
			);
		} elseif ($setting !== null) {
			SetupHelper::runOcc(
				[
					'config:system:set',
					'csrf.disabled',
					'--type',
					'boolean',
					'--value',
					$setting
				],
				$this->featureContext->getStepLineRef()
			);
		}
		return \trim($oldCSRFSetting);
	}

	/**
	 * @When user :user sets the email notification option to :setting using the API
	 *
	 * @param string $user
	 * @param string $setting
	 *
	 * @throws Exception
	 * @return void
	 */
	public function userSetsEmailNotificationOption(string $user, string $setting):void {
		$oldCSRFSetting = $this->disableCSRF();

		$fullUrl = $this->featureContext->getBaseUrl() .
				   "/index.php/apps/notifications/settings/personal/" .
				   "notifications/options";

		$response = HttpRequestHelper::sendRequest(
			$fullUrl,
			$this->featureContext->getStepLineRef(),
			"PATCH",
			$user,
			$this->featureContext->getUserPassword($user),
			['Content-Type' => 'application/json'],
			'{"email_sending_option":"' . $setting . '"}'
		);
		$this->setCSRFDotDisabled($oldCSRFSetting);

		Assert::assertEquals(
			200,
			$response->getStatusCode(),
			"could not set notification option " . $response->getReasonPhrase()
		);
		$responseDecoded = \json_decode($response->getBody());
		Assert::assertEquals(
			$responseDecoded->data->options->id,
			$user,
			"Could not set notification option! " .
			"'user' in the response is:'" .
			$responseDecoded->data->options->id . "' " .
			"but should be: '$user'"
		);
		Assert::assertEquals(
			$responseDecoded->data->options->email_sending_option,
			$setting,
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
	public function theAdminSetsEmailNotificationOption(string $setting):void {
		$this->userSetsEmailNotificationOption(
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
	public function deleteNotification(string $user, string $firstOrLast):void {
		Assert::assertNotEmpty(
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
		$this->ocsContext->userSendsToOcsApiEndpoint(
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
	public function theAdminDeletesNotification(string $firstOrLast):void {
		$this->deleteNotification(
			$this->featureContext->getAdminUsername(),
			$firstOrLast
		);
	}

	/**
	 * @When the administrator sends following notifications using the occ command
	 *
	 * @param TableNode $notificationContent table with heading subject, message, user, group , link
	 *
	 * @return void
	 */
	public function theAdministratorSendsFollowingNotificationsUsingTheOccCommand(
		TableNode $notificationContent
	):void {
		foreach ($notificationContent as $content) {
			$cmd = "notifications:generate";
			if (\array_key_exists("subject", $content)) {
				$cmd = $cmd . " '{$content['subject']}'";
			}
			if (\array_key_exists("message", $content)) {
				$cmd = $cmd . " '{$content['message']}'";
			}
			if (\array_key_exists("user", $content)) {
				$cmd = $cmd . " -u {$content['user']}";
			}
			if (\array_key_exists("group", $content)) {
				$cmd = $cmd . " -g {$content['group']}";
			}
			if (\array_key_exists("link", $content)) {
				$cmd = $cmd . " -l {$content['link']}";
			}
			$this->featureContext->runOcc(
				[$cmd]
			);
		}
	}

	/**
	 * @BeforeScenario
	 *
	 * @param BeforeScenarioScope $scope
	 *
	 * @return void
	 */
	public function setUpScenario(BeforeScenarioScope $scope):void {
		// Get the environment
		$environment = $scope->getEnvironment();
		// Get all the contexts you need in this context
		$this->featureContext = $environment->getContext('FeatureContext');
		//make sure the before scenario of featureContext runs, because it
		//registers OCSContext to all suites
		$this->featureContext->before($scope);
		$this->notificationsCoreContext = $environment->getContext(
			'NotificationsCoreContext'
		);
		$this->ocsContext = $environment->getContext('OCSContext');
		SetupHelper::init(
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			$this->featureContext->getBaseUrl(),
			$this->featureContext->getOcPath()
		);
	}
}
