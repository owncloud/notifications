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

namespace OCA\Notifications\Tests\Controller;

use OCA\Notifications\Controller\NotificationOptionsController;
use OCA\Notifications\Configuration\OptionsStorage;
use OCP\IUserSession;
use OCP\IUser;
use OCP\IRequest;
use OCP\IL10N;
use OCP\AppFramework\Http;

class NotificationOptionsControllerTest extends \Test\TestCase {
	/** @var IUserSession */
	private $userSession;
	/** @var OptionsStorage */
	private $optionsStorage;
	/** @var IRequest */
	private $request;
	/** @var NotificationOptionsController */
	private $controller;
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
		$this->request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()
			->getMock();

		$this->l10n->method('t')
			->will($this->returnCallback(function ($text, $params = []) {
				return \vsprintf($text, $params);
			}));

		$this->optionsStorage->method('getValidOptionValuesInfo')
			->willReturn([
				'email_sending_option' => [
					'values' => ['never', 'action', 'always'],
					'default' => 'action',
				],
		]);

		$this->controller = new NotificationOptionsController($this->userSession, $this->optionsStorage, $this->l10n, $this->request);
	}

	private function buildDefaultOptions($id) {
		return [
			'id' => $id,
			'email_sending_option' => 'never',
		];
	}

	private function getSuccessResponse($options) {
		return \json_encode([
			'data' => [
				'options' => $options,
				'message' => 'Saved'
			]
		]);
	}

	private function getErrorResponse($rejects = []) {
		$data = [
			'data' => [
				'message' => 'Option not supported',
				'errorCode' => 2,
				'rejects' => $rejects,
			]
		];
		return \json_encode($data);
	}

	public function emailNotificationOptionsProvider() {
		return [
			['never'],
			['action'],
			['always'],
			['randomkey'],
			['·$%·$&$%&/dfglkjdf ouer'],
		];
	}

	/**
	 * @dataProvider emailNotificationOptionsProvider
	 */
	public function testSetNotificationOptionsPartialEmailSendingOption($value) {
		$this->request->method('getParams')
			->willReturn(['email_sending_option' => $value]);

		$validKeys = ['never', 'action', 'always'];

		$mockedUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$mockedUser->method('getUID')->willReturn('testUser');

		$this->userSession->method('getUser')->willReturn($mockedUser);

		$valuesSet = [];

		$this->optionsStorage->method('isOptionValid')
			->will($this->returnCallback(function ($key, $value) use ($validKeys) {
				return \in_array($value, $validKeys, true);
			}));

		$this->optionsStorage->method('setOption')
			->will($this->returnCallback(function ($user, $key, $value) use (&$valuesSet) {
				$valuesSet = [
					'user' => $user,
					'key' => $key,
					'value' => $value,
				];
				return null;
			}));

		$this->optionsStorage->method('getOptions')
			->will($this->returnCallback(function ($userid) use (&$valuesSet) {
				if ($userid === $valuesSet['user']) {
					return [$valuesSet['key'] => $valuesSet['value']];
				} else {
					return [];
				}
			}));

		if (!\in_array($value, $validKeys, true)) {
			$this->optionsStorage->expects($this->never())
				->method('setOption');
		}

		$result = $this->controller->setNotificationOptionsPartial();

		if (\in_array($value, $validKeys, true)) {
			$this->assertEquals('testUser', $valuesSet['user']);
			$this->assertEquals('email_sending_option', $valuesSet['key']);
			$this->assertEquals($value, $valuesSet['value']);

			$expectedOptions = $this->buildDefaultOptions('testUser');
			$expectedOptions['email_sending_option'] = $value;
			$expectedValue = $this->getSuccessResponse($expectedOptions);

			$this->assertJsonStringEqualsJsonString($expectedValue, $result->render());
		} else {
			$expectedValue = $this->getErrorResponse(['email_sending_option' => $value]);
			$this->assertJsonStringEqualsJsonString($expectedValue, $result->render());
		}
	}

	public function testSetNotificationOptions() {
		$params = $this->buildDefaultOptions('testUser');
		$this->request->method('getParams')
			->willReturn($params);

		$mockedUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$mockedUser->method('getUID')->willReturn('testUser');

		$this->userSession->method('getUser')->willReturn($mockedUser);

		$valuesSet = [];
		$this->optionsStorage->method('setOption')
			->will($this->returnCallback(function ($user, $key, $value) use (&$valuesSet) {
				if (!isset($valuesSet[$user])) {
					$valuesSet[$user] = [];
				}
				$valuesSet[$user][$key] = $value;
				return null;
			}));
		$defaultOptions = $this->buildDefaultOptions('testUser');
		unset($defaultOptions['id']);

		$this->optionsStorage->method('isOptionValid')
			->will($this->returnCallback(function ($key, $value) use ($defaultOptions) {
				return \in_array($key, \array_keys($defaultOptions), true);  // don't check specific values here
			}));

		$this->optionsStorage->method('getOptions')
			->will($this->returnCallback(function ($user) use (&$valuesSet, $defaultOptions) {
				if (!isset($valuesSet[$user])) {
					return $defaultOptions;
				} else {
					return $valuesSet[$user];
				}
			}));

		$result = $this->controller->setNotificationOptions();

		$expectedValue = $this->getSuccessResponse($params);
		$this->assertJsonStringEqualsJsonString($expectedValue, $result->render());
	}

	public function testSetNotificationOptionsInsuficientData() {
		$params = $this->buildDefaultOptions('testUser');
		unset($params['email_sending_option']);
		$this->request->method('getParams')
			->willReturn($params);

		$mockedUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$mockedUser->method('getUID')->willReturn('testUser');

		$this->userSession->method('getUser')->willReturn($mockedUser);

		$result = $this->controller->setNotificationOptions();

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $result->getStatus());
		$rawData = $result->getData();
		$this->assertEquals('Incomplete data', $rawData['data']['message']);
	}

	public function testSetNotificationOptionsUnknownUser() {
		$params = $this->buildDefaultOptions('testUser');
		$this->request->method('getParams')
			->willReturn($params);

		$this->userSession->method('getUser')->willReturn(null);

		$result = $this->controller->setNotificationOptions();

		$this->assertEquals(Http::STATUS_FORBIDDEN, $result->getStatus());
		$rawData = $result->getData();
		$this->assertStringContainsString('Unknown user session', $rawData['data']['message']);
	}

	public function testGetNotificationOptions() {
		$mockedUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$mockedUser->method('getUID')->willReturn('testUser');

		$this->userSession->method('getUser')->willReturn($mockedUser);

		$defaultOptions = $this->buildDefaultOptions('testUser');
		$this->optionsStorage->method('getOptions')
			->will($this->returnCallback(function ($user) use ($defaultOptions) {
				unset($defaultOptions['id']);
				return $defaultOptions;
			}));

		$result = $this->controller->getNotificationOptions();
		$expectedValue = ['data' => ['options' => $defaultOptions]];
		$this->assertJsonStringEqualsJsonString(\json_encode($expectedValue), $result->render());
	}

	public function testGetNotificationOptionsUnknownUser() {
		$params = $this->buildDefaultOptions('testUser');
		$this->request->method('getParams')
			->willReturn($params);

		$this->userSession->method('getUser')->willReturn(null);

		$result = $this->controller->getNotificationOptions();

		$this->assertEquals(Http::STATUS_FORBIDDEN, $result->getStatus());
		$rawData = $result->getData();
		$this->assertStringContainsString('Unknown user session', $rawData['data']['message']);
	}
}
