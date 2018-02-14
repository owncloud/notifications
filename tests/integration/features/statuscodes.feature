Feature: statuscodes
  Background:
    Given user "test1" has been created
    Given as user "test1"

  Scenario: Status code when reading notifications with notifiers and without notifications
    When the user sends HTTP method "GET" to API endpoint "/apps/notifications/api/v1/notifications?format=json"
    Then the HTTP status code should be "200"
    And list of notifications has 0 entries

  Scenario: Status code when reading notifications with notifiers and notification
    Given user "test1" has notifications
    When the user sends HTTP method "GET" to API endpoint "/apps/notifications/api/v1/notifications?format=json"
    Then the HTTP status code should be "200"
    And list of notifications has 1 entries

  Scenario: Status code when reading notifications with notifiers and notifications
    Given user "test1" has notifications
    Given user "test1" has notifications
    Given user "test1" has notifications
    When the user sends HTTP method "GET" to API endpoint "/apps/notifications/api/v1/notifications?format=json"
    Then the HTTP status code should be "200"
    And list of notifications has 3 entries
