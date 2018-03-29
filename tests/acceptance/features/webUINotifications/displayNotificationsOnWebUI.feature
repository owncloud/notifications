@webUI @insulated @disablePreviews
Feature: display notifications on the webUI

As a user
I want to see my notifications on the webUI
So that I can stay informed

	Background:
		Given these users have been created:
			|username|password|displayname|email       |
			|user1   |1234    |User One   |u1@oc.com.np|
		And the user has browsed to the login page
		And the user has logged in with username "user1" and password "1234" using the webUI
		And using API version "2"

	Scenario: Create notifications
		When user "user1" is sent a notification with
			| app         | notificationsacceptancetesting  |
			| timestamp   | 144958517                       |
			| subject     | Acceptance Testing              |
			| link        | https://owncloud.org/blog       |
			| message     | Notifications in ownCloud       |
			| object_type | blog                            |
			| object_id   | 9483                            |
		And user "user1" is sent a notification with
			| app         | notificationsacceptancetesting  |
			| timestamp   | 144958517                       |
			| subject     | UI tests                        |
			| link        | http://owncloud.org/            |
			| message     | second notification             |
			| object_type | blog                            |
			| object_id   | 9484                            |
		Then user "user1" should see 2 notifications on the webUI with these details
			| title               | link                       | message                   |
			| Acceptance Testing  | https://owncloud.org/blog  | Notifications in ownCloud |
			| UI tests            | http://owncloud.org/       | second notification       |
