@api
Feature: delete-notifications

  Background:
    Given user "test1" has been created with default attributes and skeleton files
    And using OCS API version "2"

  Scenario: Delete first notification
    When user "test1" is sent a notification
    And user "test1" is sent another notification
    And user "test1" is sent another notification
    Then user "test1" should have 3 notifications
    When user "test1" deletes the first notification
    Then user "test1" should have 2 notifications missing the first one

  Scenario: Delete last notification
    When user "test1" is sent a notification
    And user "test1" is sent another notification
    And user "test1" is sent another notification
    Then user "test1" should have 3 notifications
    When user "test1" deletes the last notification
    Then user "test1" should have 2 notifications missing the last one
