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

namespace OCA\Notifications\Tests\Unit\Configuration;

use OCP\IConfig;
use OCA\Notifications\Configuration\OptionsStorage;

class OptionsStorageTest extends \Test\TestCase {
	/** @var IConfig */
	private $config;
	/** @var OptionsStorage */
	private $optionsStorage;

	protected function setUp() {
		parent::setUp();

		$this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();

		$this->optionsStorage = new OptionsStorage($this->config);
	}

	public function testGetValidOptionValuesInfo() {
		$expected = [
			'email_sending_option' => [
				'values' => ['never', 'action', 'always'],
				'default' => 'action',
			],
		];
		$this->assertEquals($expected, $this->optionsStorage->getValidOptionValuesInfo());
	}

	public function testGetOptions() {
		$this->config->method('getUserValue')
			->will($this->returnValueMap([
				['user1', 'notifications', 'email_sending_option', 'action', 'randomValue']
		]));
		$expected = ['email_sending_option' => 'randomValue'];
		$this->assertEquals($expected, $this->optionsStorage->getOptions('user1'));
	}

	public function validOptionProvider() {
		return [
			['email_sending_option', 'never'],
			['email_sending_option', 'action'],
			['email_sending_option', 'always'],
		];
	}

	/**
	 * @dataProvider validOptionProvider
	 */
	public function testSetOption($key, $value) {
		$this->assertTrue($this->optionsStorage->setOption('user1', $key, $value));
	}

	public function invalidOptionProvider() {
		return [
			['email_sending_option', 'VALUEWRONG'],
			['KEYWRONG', 'action'],
			['KEYWRONG', 'VALUEWRONG'],
		];
	}

	/**
	 * @dataProvider invalidOptionProvider
	 */
	public function testSetOptionWrong($key, $value) {
		$this->assertFalse($this->optionsStorage->setOption('user1', $key, $value));
	}

	/**
	 * @dataProvider invalidOptionProvider
	 */
	public function testIsOptionValid($key, $value) {
		$this->assertFalse($this->optionsStorage->isOptionValid($key, $value));
	}

	public function testGetUserLanguage() {
		$this->config->method('getUserValue')
			->will($this->returnValueMap([
				['user1', 'core', 'lang', null, 'de_DE']
		]));
		$this->assertEquals('de_DE', $this->optionsStorage->getUserLanguage('user1'));
	}
}
