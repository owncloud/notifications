@api @mailhog
Feature: notifications-content

  Background:
    Given user "user1" has been created with default attributes
    And using OCS API version "2"

  Scenario: Create notification
    When user "user1" sets the email notification option to "always" using the API
    And user "user1" is sent a notification with
      | app         | notificationsacceptancetesting                                            |
      | timestamp   | 144958517                                                                 |
      | subject     | Acceptance Testing                                                        |
      | link        | https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/ |
      | message     | About Activities and Notifications in ownCloud                            |
      | object_type | blog                                                                      |
      | object_id   | 9483                                                                      |
    Then the email address "user1@example.org" should have received an email with the body containing
      """
      Hello,
      About Activities and Notifications in ownCloud

      See https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/ on ownCloud for more information
      """
    And the email address "user1@example.org" should have received an email with the body containing
      """
      See <a href="https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/">https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/</a> on ownCloud for more information</td>
      """
