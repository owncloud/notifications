@api
Feature: delete-notifications

  Background:
    Given user "test1" has been created
    Given as user "test1"

  Scenario: Delete first notification
    When user "test1" is sent a notification
    And user "test1" is sent another notification
    And user "test1" is sent another notification
    Then user "test1" should have 3 notifications
    When the user deletes the first notification
    Then user "test1" should have 2 notifications missing the first one

  Scenario: Delete last notification
    When user "test1" is sent a notification
    And user "test1" is sent another notification
    And user "test1" is sent another notification
    Then user "test1" should have 3 notifications
    When the user deletes the last notification
    Then user "test1" should have 2 notifications missing the last one
