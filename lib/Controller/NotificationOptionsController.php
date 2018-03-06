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

namespace OCA\Notifications\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUserSession;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;

class NotificationOptionsController extends Controller {
	const ERROR_CODE_MISSING_USER_SESSION = 1;
	const ERROR_CODE_OPTION_NOT_SUPPORTED = 2;
	const ERROR_CODE_INCOMPLETE_DATA = 3;

	/** @var IUserSession */
	private $userSession;
	/** @var IConfig */
	private $config;
	/** @var IL10N */
	private $l10n;

	private $validOptionValues = [
		'email_sending_option' => [
			'values' => ['never', 'action', 'always'],
			'default' => 'action',
		],
	];

	public function __construct(IUserSession $userSession, IConfig $config, IL10N $l10n, IRequest $request) {
		parent::__construct('notifications', $request);
		$this->userSession = $userSession;
		$this->config = $config;
		$this->l10n = $l10n;
	}

	/**
	 * Get the supported options. It will return an array like the following:
	 * [ <option-key> => ['values' => [<s1>, <s2>, <s3>], 'default' => <s1> ]]
	 * The <s1>, <s2>, etc are supported values that can be placed in that key.
	 */
	public function getValidOptionValuesInfo() {
		return $this->validOptionValues;
	}

	/**
	 * @param array $options such as [option1 => value1, option2 => value2]. Valid options and their
	 * corresponding values must be defined in the $validOptionValues private variable.
	 * @return JSONResponse
	 */
	private function validateAndSave($options) {
		$userObject = $this->userSession->getUser();
		if ($userObject === null) {
			return new JSONResponse([
				'data' => [
					'message' => (string)$this->l10n->t('Unknown user session. It is not possible to set the option'),
					'errorCode' => self::ERROR_CODE_MISSING_USER_SESSION,
				]
			], Http::STATUS_FORBIDDEN);
		}

		$missingKeysError = [];
		$invalidValuesError = [];
		$validOptions = [];
		foreach ($options as $key => $value) {
			if (isset($this->validOptionValues[$key])) {
				if (!in_array($value, $this->validOptionValues[$key]['values'], true)) {
					$invalidValuesError[$key] = $value;
				} else {
					$validOptions[$key] = $value;
				}
			} else {
				$missingKeysError[$key] = $value;
			}
		}

		// reject everything if any option isn't valid
		if (!empty($invalidValuesError) || !empty($missingKeysError)) {
			return new JSONResponse([
				'data' => [
					'message' => (string)$this->l10n->t('Option not supported'),
					'errorCode' => self::ERROR_CODE_OPTION_NOT_SUPPORTED,
					'invalid' => $invalidValuesError,
					'missing' => $missingKeysError,
				]
			], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$userid = $userObject->getUID();
		foreach ($validOptions as $option => $optionValue) {
			$this->config->setUserValue($userid, 'notifications', $option, $optionValue);
		}

		return new JSONResponse([
			'data' => [
				'message' => (string)$this->l10n->t('Saved'),
				'options' => $this->getOptionsFromConfig($userid),
			]
		]);
	}

	/**
	 * @NoAdminRequired
	 * @return JSONResponse
	 */
	public function setNotificationOptionsPartial() {
		$options = $this->fetchParamsFromRequest();
		return $this->validateAndSave($options);
	}

	/**
	 * @NoAdminRequired
	 * @return JSONResponse
	 */
	public function setNotificationOptions() {
		$options = $this->fetchParamsFromRequest();
		// check that all the keys are filled
		foreach ($this->validOptionValues as $key => $value) {
			if (!isset($options[$key])) {
				return new JSONResponse([
					'data' => [
						'message' => (string)$this->l10n->t('Incomplete data'),
						'errorCode' => self::ERROR_CODE_INCOMPLETE_DATA,
					]
				], Http::STATUS_BAD_REQUEST);
			}
		}
		return $this->validateAndSave($options);
	}

	/**
	 * @NoAdminRequired
	 * Get the list of options and their corresponding values (or default values for each unset option)
	 */
	public function getNotificationOptions() {
		$userObject = $this->userSession->getUser();
		if ($userObject === null) {
			return new JSONResponse([
				'data' => [
					'message' => (string)$this->l10n->t('Unknown user session. It is not possible to set the option'),
					'errorCode' => self::ERROR_CODE_MISSING_USER_SESSION,
				]
			], Http::STATUS_FORBIDDEN);
		}

		$data = $this->getOptionsFromConfig($userObject->getUID());

		return new JSONResponse([
			'data' => [
				'options' => $data,
			],
		]);
	}

	/**
	 * Get the options for the user from the DB. An "id" key with the user name will be added
	 * @return array [optionKey => optionValue]. An additional "id" => $userid key-value pair will be
	 * included.
	 */
	private function getOptionsFromConfig($userid) {
		$data = ['id' => $userid];
		foreach ($this->validOptionValues as $option => $optionValue) {
			$data[$option] = $this->config->getUserValue($userid, 'notifications', $option, $optionValue['default']);
		}
		return $data;
	}

	private function fetchParamsFromRequest() {
		$options = $this->request->getParams();
		foreach (array_keys($options) as $key) {
			if (@$key[0] === '_') {
				// the condition will be evaluated to false if $key is an empty string or a number
				// just supress the warning.
				unset($options[$key]);
			}
		}
		// ignore a possible "id" key since we'll use the session for this for PUT requests
		unset($options['id']);
		return $options;
	}
}
