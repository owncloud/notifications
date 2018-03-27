<?php
/**
 * ownCloud
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Copyright (c) 2017 Artur Neumann artur@jankaritech.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
require_once 'bootstrap.php';

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\RawMinkContext;
use Page\NotificationsEnabledOwncloudPage;

/**
 * Context for Notifications App
 *
 */
class WebUINotificationsContext extends RawMinkContext implements Context {
	/**
	 * 
	 * @var NotificationsEnabledOwncloudPage
	 */
	private $owncloudPage;
	
	/**
	 * 
	 * @param NotificationsEnabledOwncloudPage $owncloudPage
	 */
	public function __construct(
		NotificationsEnabledOwncloudPage $owncloudPage
	) {
			$this->owncloudPage = $owncloudPage;
	}
	
	/**
	 * 
	 * @Then /^user "([^"]*)" should see (\d+) notification(?:s|) on the webUI with these details$/
	 * 
	 * @param string $user
	 * @param int $number
	 * @param TableNode $expectedNotifications
	 * 
	 * @return void
	 */
	public function assertNotificationsOnWebUI(
		$user, $number, TableNode $expectedNotifications
	) {
		$this->getSession()->reload();
		$this->owncloudPage->waitTillPageIsLoaded($this->getSession());
		$this->owncloudPage->waitForNotifications();
		$notificationsDialog = $this->owncloudPage->openNotifications();
		$notifications = $notificationsDialog->getAllNotifications();
		PHPUnit_Framework_Assert::assertEquals(
			$number,
			count($notifications),
			"expected $number notifications, found " . count($notifications)
		);
		foreach ($expectedNotifications as $expectedNotification) {
			foreach ($notifications as $notification) {
				$found = false;
				foreach ($expectedNotification as $expectedKey => $expectedValue) {
					if ($notification[$expectedKey] === $expectedValue) {
						$found = true;
					} else {
						$found = false;
						break;
					}
				}
				if ($found) {
					break;
				}
			}
			if (!$found) {
				PHPUnit_Framework_Assert::fail(
					"could not find expected notification: " .
					print_r($expectedNotification, true) .
					" in viewed notifications: " .
					print_r($notifications, true)
				);
			}
		}
	}
}