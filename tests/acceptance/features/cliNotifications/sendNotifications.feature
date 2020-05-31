@cli @notification-app-required
Feature: send notifications using the occ command

  As an administrator
  I want to be able to send notifications to the owncloud users
  So that I can inform them about the necessary things

  Scenario: administrator should be able to send a notification with subject and message to a user
    Given user "Alice" has been created with default attributes and skeleton files
    When the administrator sends following notifications using the occ command
      | subject       | message                                          | user  |
      | Quota updated | Congratulations your oC quota has been increased | Alice |
    Then the command should have been successful
    And user "Alice" should have 1 notification
    And the last notification of user "Alice" should match
      | key     | regex                                            |
      | subject | Quota updated                                    |
      | message | Congratulations your oC quota has been increased |

  Scenario: administrator should be able to send a notification with a link
    Given user "Alice" has been created with default attributes and skeleton files
    When the administrator sends following notifications using the occ command
      | subject       | message                                          | user  | link                                                                      |
      | Quota updated | Congratulations your oC quota has been increased | Alice | https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/ |
    Then the command should have been successful
    And user "Alice" should have 1 notification
    And the last notification of user "Alice" should match
      | key     | regex                                                                     |
      | subject | Quota updated                                                             |
      | message | Congratulations your oC quota has been increased                          |
      | link    | https://owncloud.org/blog/about-activities-and-notifications-in-owncloud/ |

  Scenario: administrator should be able to send a notification with only the subject to a user
    Given user "Alice" has been created with default attributes and skeleton files
    When the administrator sends following notifications using the occ command
      | subject       | user  |
      | Quota updated | Alice |
    Then the command should have been successful
    And user "Alice" should have 1 notification
    And the last notification of user "Alice" should match
      | key     | regex         |
      | subject | Quota updated |

  Scenario: administrator should be able to send a notification with subject and message to a group
    Given these users have been created with skeleton files:
      | username |
      | Alice    |
      | Brian    |
      | Carol    |
    And group "grp1" has been created
    And user "Alice" has been added to group "grp1"
    And user "Brian" has been added to group "grp1"
    When the administrator sends following notifications using the occ command
      | subject       | group |
      | Quota updated | grp1  |
    Then the command should have been successful
    And user "Alice" should have 1 notification
    And user "Brian" should have 1 notification
    And user "Carol" should have 0 notification

  Scenario: administrator should be able to send a notification with subject and message to a group
    Given user "Alice" has been created with default attributes and skeleton files
    And group "grp1" has been created
    And user "Alice" has been added to group "grp1"
    When the administrator sends following notifications using the occ command
      | subject       | message                                          | group |
      | Quota updated | Congratulations your oC quota has been increased | grp1  |
    Then the command should have been successful
    And user "Alice" should have 1 notification
    And the last notification of user "Alice" should match
      | key     | regex                                            |
      | subject | Quota updated                                    |
      | message | Congratulations your oC quota has been increased |

  Scenario: administrator sends more than one notifications to a user
    Given user "Alice" has been created with default attributes and skeleton files
    When the administrator sends following notifications using the occ command
      | subject           | message                                             | user  |
      | Quota updated     | Congratulations your oC quota has been increased    | Alice |
      | Meeting postponed | It is to notify that the meeting has been posponded | Alice |
    Then the command should have been successful
    And user "Alice" should have 2 notifications
