# Notifications

Notification backend and UI for the notification panel/icon.
Used for notifications of other apps ([announcementcenter](https://github.com/owncloud/announcementcenter), [federatedfilesharing](https://github.com/owncloud/core/tree/master/apps/federatedfilesharing) etc.)

Trigger CI

## QA metrics on master branch:

[![Build Status](https://travis-ci.org/owncloud/notifications.svg?branch=master)](https://travis-ci.org/owncloud/notifications)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/owncloud/notifications/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/owncloud/notifications/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/owncloud/notifications/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/owncloud/notifications/?branch=master)

## Screenshots

### No notifications (Sample)

**Note:**
In ownCloud 8.2 the app hides itself, when there is no app registered,
that creates notifications. In this case the bell and the dropdown are not
accessible.

![Build Status](img/sample-empty.png)

### New notifications (Sample)

![Build Status](img/sample-new.png)

## Notification workflow

For information how to make your app interact with the notifications app, see
[Sending and processing/"mark as read" notifications as an ownCloud App](https://github.com/owncloud/notifications/blob/master/docs/notification-workflow.md)
in the wiki.

If you want to present notifications as a client, see [Reading and deleting notifications as an ownCloud Client](https://github.com/owncloud/notifications/blob/master/docs/ocs-endpoint-v1.md).
