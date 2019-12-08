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

namespace OCA\Notifications\Tests\Panels\Personal;

use OCP\IUserSession;
use OCP\IUser;
use OCP\IL10N;
use OCA\Notifications\Configuration\OptionsStorage;
use OCA\Notifications\Panels\Personal\NotificationsPanel;

class NotificationsPanelTest extends \Test\TestCase {
	/** @var OptionsStorage */
	private $optionsStorage;
	/** @var IUserSession */
	private $userSession;
	/** @var NotificationsPanel */
	private $notificationsPanel;
	/** @var IL10N */
	private $l10n;

	protected function setUp(): void {
		parent::setUp();
		$this->userSession = $this->getMockBuilder(IUserSession::class)
			->disableOriginalConstructor()
			->getMock();
		$this->optionsStorage = $this->getMockBuilder(OptionsStorage::class)
			->disableOriginalConstructor()
			->getMock();
		$this->l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();

		$this->l10n->method('t')
			->will($this->returnCallback(function ($text, $params = []) {
				return \vsprintf($text, $params);
			}));

		$this->notificationsPanel = new NotificationsPanel($this->optionsStorage, $this->userSession, $this->l10n);
	}

	public function testGetPriority() {
		$this->assertEquals(90, $this->notificationsPanel->getPriority());
	}

	public function testGetSectionID() {
		$this->assertEquals('general', $this->notificationsPanel->getSectionID());
	}

	public function panelValueProvider() {
		return [
			['never'],
			['action'],
			['always'],
			['randomValue'],
		];
	}

	/**
	 * @dataProvider panelValueProvider
	 */
	public function testGetPanelDefault($selectedValue) {
		$mockedUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$mockedUser->method('getUID')->willReturn('testUser');

		$this->userSession->method('getUser')->willReturn($mockedUser);

		$this->optionsStorage->method('getOptions')->willReturn(['email_sending_option' => $selectedValue]);

		$page = $this->notificationsPanel->getPanel()->fetchPage();
		$this->assertContains('<h2 class="app-name">Mail Notifications</h2>', $page);
		if (\in_array($selectedValue, ['never', 'action', 'always'], true)) {
			$this->assertContains("<option value=\"$selectedValue\" selected=\"selected\">", $page);
		} else {
			$this->assertContains("<option value=\"$selectedValue\" selected=\"selected\">Choose an option</option>", $page);
		}
	}

	public function testGetPanelMissingUserSession() {
		$this->userSession->method('getUser')->willReturn(null);

		$this->optionsStorage->method('getOptions')->willReturn(['email_sending_option' => 'always']);

		$page = $this->notificationsPanel->getPanel()->fetchPage();
		$this->assertContains('not possible to get your session', $page);
		$this->assertNotContains('<select id="email_sending_option">', $page);
	}
}
