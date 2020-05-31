@api
Feature: delete-notifications

  Background:
    Given user "Alice" has been created with default attributes and skeleton files
    And using OCS API version "2"

  Scenario: Delete first notification
    When user "Alice" is sent a notification
    And user "Alice" is sent another notification
    And user "Alice" is sent another notification
    Then user "Alice" should have 3 notifications
    When user "Alice" deletes the first notification
    Then user "Alice" should have 2 notifications missing the first one

  Scenario: Delete last notification
    When user "Alice" is sent a notification
    And user "Alice" is sent another notification
    And user "Alice" is sent another notification
    Then user "Alice" should have 3 notifications
    When user "Alice" deletes the last notification
    Then user "Alice" should have 2 notifications missing the last one
