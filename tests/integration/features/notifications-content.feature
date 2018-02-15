Feature: notifications-content

  Background:
    Given user "test1" has been created
    Given as user "test1"

  Scenario: Create notification
    When user "test1" is sent a notification with
      | app         | notificationsintegrationtesting                                           |
      | timestamp   | 144958517                                                                 |
      | subject     | Integration testing                                                       |
      | link        | https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/ |
      | message     | About Activities and Notifications in ownCloud                            |
      | object_type | blog                                                                      |
      | object_id   | 9483                                                                      |
    Then user "test1" should have 1 notification
    And the last notification should match
      | app         | notificationsintegrationtesting                                           |
      | datetime    | 1974-08-05T18:15:17+00:00                                                 |
      | subject     | Integration testing                                                       |
      | link        | https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/ |
      | message     | About Activities and Notifications in ownCloud                            |
      | object_type | blog                                                                      |
      | object_id   | 9483                                                                      |

  Scenario: Create different notification
    When user "test1" is sent a notification with
      | app         | notificationsintegrationtesting                                               |
      | timestamp   | 144958515                                                                     |
      | subject     | Testing integration                                                           |
      | link        | https://github.com/owncloud/notifications/blob/master/docs/ocs-endpoint-v1.md |
      | message     | Reading and deleting notifications as a Client                                |
      | object_type | repo                                                                          |
      | object_id   | notifications                                                                 |
    Then user "test1" should have 1 notification
    And the last notification should match
      | app         | notificationsintegrationtesting                                               |
      | datetime    | 1974-08-05T18:15:15+00:00                                                     |
      | subject     | Testing integration                                                           |
      | link        | https://github.com/owncloud/notifications/blob/master/docs/ocs-endpoint-v1.md |
      | message     | Reading and deleting notifications as a Client                                |
      | object_type | repo                                                                          |
      | object_id   | notifications                                                                 |
