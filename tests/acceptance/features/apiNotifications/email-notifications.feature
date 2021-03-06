@api @mailhog
Feature: notifications-content

  Background:
    Given user "Alice" has been created with default attributes and without skeleton files
    And using OCS API version "2"

  Scenario: Create notification
    When user "Alice" sets the email notification option to "always" using the API
    And user "Alice" is sent a notification with
      | app         | notificationsacceptancetesting                                            |
      | timestamp   | 144958517                                                                 |
      | subject     | Acceptance Testing                                                        |
      | link        | https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/ |
      | message     | About Activities and Notifications in ownCloud                            |
      | object_type | blog                                                                      |
      | object_id   | 9483                                                                      |
    Then the email address "alice@example.org" should have received an email with the body containing
      """
      Hello,
      About Activities and Notifications in ownCloud

      See https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/ on ownCloud for more information
      """
    And the email address "alice@example.org" should have received an email with the body containing
      """
      See <a href="https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/">https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/</a> on ownCloud for more information</td>
      """
