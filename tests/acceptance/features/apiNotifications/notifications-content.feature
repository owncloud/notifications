@api
Feature: notifications-content

  Background:
    Given user "test1" has been created with default attributes and skeleton files
    And using OCS API version "2"

  Scenario: Create notification
    When user "test1" is sent a notification with
      | app         | notificationsacceptancetesting                                            |
      | timestamp   | 144958517                                                                 |
      | subject     | Acceptance Testing                                                        |
      | link        | https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/ |
      | message     | About Activities and Notifications in ownCloud                            |
      | object_type | blog                                                                      |
      | object_id   | 9483                                                                      |
    Then user "test1" should have 1 notification
    And the last notification of user "test1" should match
      | app         | notificationsacceptancetesting                                            |
      | datetime    | 1974-08-05T18:15:17+00:00                                                 |
      | subject     | Acceptance Testing                                                        |
      | link        | https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/ |
      | message     | About Activities and Notifications in ownCloud                            |
      | object_type | blog                                                                      |
      | object_id   | 9483                                                                      |

  Scenario: Create different notification
    When user "test1" is sent a notification with
      | app         | notificationsacceptancetesting                                                |
      | timestamp   | 144958515                                                                     |
      | subject     | Testing Acceptance                                                            |
      | link        | https://github.com/owncloud/notifications/blob/master/docs/ocs-endpoint-v1.md |
      | message     | Reading and deleting notifications as a Client                                |
      | object_type | repo                                                                          |
      | object_id   | notifications                                                                 |
    Then user "test1" should have 1 notification
    And the last notification of user "test1" should match
      | app         | notificationsacceptancetesting                                                |
      | datetime    | 1974-08-05T18:15:15+00:00                                                     |
      | subject     | Testing Acceptance                                                            |
      | link        | https://github.com/owncloud/notifications/blob/master/docs/ocs-endpoint-v1.md |
      | message     | Reading and deleting notifications as a Client                                |
      | object_type | repo                                                                          |
      | object_id   | notifications                                                                 |
