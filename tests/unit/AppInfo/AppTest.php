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

namespace OCA\Notifications\Tests\Unit\AppInfo;

use OCA\Notifications\Tests\Unit\TestCase;
use OCP\IUser;

/**
 * Class AppTest
 *
 * @group DB
 * @package OCA\Notifications\Tests\AppInfo
 */
class AppTest extends TestCase {
	/** @var \OCP\Notification\IManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $manager;
	/** @var \OCP\IRequest|\PHPUnit\Framework\MockObject\MockObject */
	protected $request;
	/** @var \OCP\IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	protected $session;

	protected function setUp(): void {
		parent::setUp();

		$this->manager = $this->getMockBuilder('OCP\Notification\IManager')
			->disableOriginalConstructor()
			->getMock();

		$this->request = $this->getMockBuilder('OCP\IRequest')
			->disableOriginalConstructor()
			->getMock();

		$this->session = $this->getMockBuilder('OC\User\Session')
			->disableOriginalConstructor()
			->getMock();

		$this->overwriteService('NotificationManager', $this->manager);
		$this->overwriteService('Request', $this->request);
		$this->overwriteService('UserSession', $this->session);
	}

	protected function tearDown(): void {
		$this->restoreService('NotificationManager');
		$this->restoreService('Request');
		$this->restoreService('UserSession');

		parent::tearDown();
	}

	public function dataLoadingJSAndCSS() {
		$user = $this->getMockBuilder('OCP\IUser')
			->disableOriginalClone()
			->getMock();

		return [
			['/index.php', '/apps/files', $user, true],
			['/index.php', '/apps/files', null, false],
			['/remote.php', '/webdav', $user, false],
			['/index.php', '/s/1234567890123', $user, false],
			['/index.php', '/login/selectchallenge', $user, false],
		];
	}

	/**
	 * @dataProvider dataLoadingJSAndCSS
	 * @param string $scriptName
	 * @param string $pathInfo
	 * @param IUser|null $user
	 * @param bool $scriptsAdded
	 */
	public function testLoadingJSAndCSS($scriptName, $pathInfo, $user, $scriptsAdded) {
		$this->request->expects($this->any())
			->method('getScriptName')
			->willReturn($scriptName);
		$this->request->expects($this->any())
			->method('getPathInfo')
			->willReturn($pathInfo);
		$this->session->expects($this->once())
			->method('getUser')
			->willReturn($user);

		\OC_Util::$scripts = [];
		\OC_Util::$styles = [];

		include(__DIR__ . '/../../../appinfo/app.php');

		if ($scriptsAdded) {
			$this->assertNotEmpty(\OC_Util::$scripts);
			$this->assertNotEmpty(\OC_Util::$styles);
		} else {
			$this->assertEmpty(\OC_Util::$scripts);
			$this->assertEmpty(\OC_Util::$styles);
		}
	}
}
