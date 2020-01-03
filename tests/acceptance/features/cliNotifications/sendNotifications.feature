@cli @notification-app-required
Feature: send notifications using the occ command

  As an administrator
  I want to be able to send notifications to the owncloud users
  So that I can inform them about the necessary things

  Scenario: administrator should be able to send a notification with subject and message to a user
    Given user "user0" has been created with default attributes and skeleton files
    When the administrator sends following notifications using the occ command
      | subject       | message                                          | user  |
      | Quota updated | Congratulations your oC quota has been increased | user0 |
    Then the command should have been successful
    And user "user0" should have 1 notification
    And the last notification of user "user0" should match
      | key     | regex                                            |
      | subject | Quota updated                                    |
      | message | Congratulations your oC quota has been increased |

  Scenario: administrator should be able to send a notification with a link
    Given user "user0" has been created with default attributes and skeleton files
    When the administrator sends following notifications using the occ command
      | subject       | message                                          | user  | link                                                                      |
      | Quota updated | Congratulations your oC quota has been increased | user0 | https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/ |
    Then the command should have been successful
    And user "user0" should have 1 notification
    And the last notification of user "user0" should match
      | key     | regex                                                                     |
      | subject | Quota updated                                                             |
      | message | Congratulations your oC quota has been increased                          |
      | link    | https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/ |

  Scenario: administrator should be able to send a notification with only the subject to a user
    Given user "user0" has been created with default attributes and skeleton files
    When the administrator sends following notifications using the occ command
      | subject       | user  |
      | Quota updated | user0 |
    Then the command should have been successful
    And user "user0" should have 1 notification
    And the last notification of user "user0" should match
      | key     | regex         |
      | subject | Quota updated |

  Scenario: administrator should be able to send a notification with subject and message to a group
    Given these users have been created with skeleton files:
      | username |
      | user0    |
      | user1    |
      | user2    |
    And group "grp1" has been created
    And user "user0" has been added to group "grp1"
    And user "user1" has been added to group "grp1"
    When the administrator sends following notifications using the occ command
      | subject       | group |
      | Quota updated | grp1  |
    Then the command should have been successful
    And user "user0" should have 1 notification
    And user "user1" should have 1 notification
    And user "user2" should have 0 notification

  Scenario: administrator should be able to send a notification with subject and message to a group
    Given user "user0" has been created with default attributes and skeleton files
    And group "grp1" has been created
    And user "user0" has been added to group "grp1"
    When the administrator sends following notifications using the occ command
      | subject       | message                                          | group |
      | Quota updated | Congratulations your oC quota has been increased | grp1  |
    Then the command should have been successful
    And user "user0" should have 1 notification
    And the last notification of user "user0" should match
      | key     | regex                                            |
      | subject | Quota updated                                    |
      | message | Congratulations your oC quota has been increased |

  Scenario: administrator sends more than one notifications to a user
    Given user "user0" has been created with default attributes and skeleton files
    When the administrator sends following notifications using the occ command
      | subject           | message                                             | user  |
      | Quota updated     | Congratulations your oC quota has been increased    | user0 |
      | Meeting postponed | It is to notify that the meeting has been posponded | user0 |
    Then the command should have been successful
    And user "user0" should have 2 notifications