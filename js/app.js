/**
 * ownCloud - Notifications
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Tom Needham <tom@owncloud.com>
 * @copyright Tom Needham 2015
 */

(function() {

	if (!OCA.Notifications) {
		OCA.Notifications = {};
	}

	OCA.Notifications = {

		notifications: {},

		num: 0,

		pollInterval: 30000, // milliseconds

		open: false,

		$button: null,

		$container: null,

		$notifications: null,

		interval: null,

		lastKnownId: null,

		initialise: function() {
			// Go!

			// Setup elements
			this.$notifications = $('<div class="notifications"></div>');
			this.$button = $('<div class="notifications-button menutoggle"><img class="svg" alt="' + t('notifications', 'Notifications') + '" src="' + OC.imagePath('notifications', 'notifications') + '"></div>');
			this.$container = $('<div class="notification-container"></div>');
			var $wrapper = $('<div class="notification-wrapper"></div>');

			// Empty content dropdown
			var $headLine = $('<h2></h2>');
			$headLine.text(t('notifications', 'No notifications'));
			var $emptyContent = $('<div class="emptycontent"></div>');
			$emptyContent.append($headLine);
			this.$container.append($emptyContent);

			this.$notifications.append(this.$button);
			this.$notifications.append(this.$container);
			this.$container.append($wrapper);

			// Add to the UI
			$('form.searchbox').before(this.$notifications);

			// Initial call to the notification endpoint
			var self = this;
			this.fetchDescendentV2(
				OC.generateUrl('apps/notifications/api/v2/notifications?limit=3&format=json'),
				function(result, textStatus, jqxhr) {
					self.updateLastKnowId(jqxhr.getResponseHeader('OC-Last-Notification'));
					if (result.ocs.data.notifications.length > 0) {
						self.updateLastShownId(result.ocs.data.notifications[0].notification_id);
					}
			});

			// Bind the button click event
			OC.registerMenu(this.$button, this.$container);
			this.$button.on('click', this._onNotificationsButtonClick);

			this.$container.on('click', '.notification-action-button', _.bind(this._onClickAction, this));
			this.$container.on('click', '.notification-delete', _.bind(this._onClickDismissNotification, this));

			// Setup the background checker
			this.restartPolling();

			if ('Notification' in window && Notification.permission === 'default') {
				Notification.requestPermission();
			}
		},

		_onClickDismissNotification: function(event) {
			event.preventDefault();
			var self = this,
				$target = $(event.target),
				$notification = $target.closest('.notification'),
				id = $notification.attr('data-id');

			$notification.fadeOut(OC.menuSpeed);

			$.ajax({
				url: OC.generateUrl('apps/notifications/api/v2/notifications/' + id),
				type: 'DELETE',
				success: function(data) {
					self._removeNotification(id);
				},
				error: function() {
					$notification.fadeIn(OC.menuSpeed);
					OC.Notification.showTemporary('Failed to perform action');
				}
			});
		},

		_onClickAction: function(event) {
			event.preventDefault();
			var self = this;
			var $target = $(event.target);
			var $notification = $target.closest('.notification');
			var actionType = $target.attr('data-type') || 'GET';
			var actionUrl = $target.attr('data-href');

			$notification.fadeOut(OC.menuSpeed);

			$.ajax({
				url: actionUrl,
				type: actionType,
				success: function(data) {
					$('body').trigger(new $.Event('OCA.Notification.Action', {
						notification: self.notifications[$notification.attr('data-id')],
						action: {
							url: actionUrl,
							type: actionType
						}
					}));
					self._removeNotification($notification.attr('data-id'));
				},
				error: function() {
					$notification.fadeIn(OC.menuSpeed);
					OC.Notification.showTemporary('Failed to perform action');
				}
			});

		},

		_removeNotification: function(id) {
			var $notification = this.$container.find('.notification[data-id=' + id + ']');
			delete OCA.Notifications.notifications[id];

			$notification.remove();
			if (_.keys(OCA.Notifications.notifications).length === 0 &&
					this.$container.find('div.notification-controls').length === 0) {
				this._onHaveNoNotifications();
			}
		},

		/**
		 * Handles the notification button click event
		 */
		_onNotificationsButtonClick: function() {
			// Show a popup
			OC.showMenu(null, OCA.Notifications.$container);
		},

		/**
		 * Restart the polling. Use "clearInterval(this.interval)" to stop the polling
		 */
		restartPolling: function() {
			this.interval = setInterval(_.bind(this.pollingV2, this), this.pollInterval);
		},

		/**
		 * Make a GET request to the target url. Consider the result of the request as a list of
		 * notifications sorted from newer to older and draw those notifications accordingly in the UI.
		 * @param {string} url the url of the request, it should be apps/notifications/api/v2/notifications?fetch=desc
		 * with additional parameters in the url
		 * @param {function} successCallback the callback that will be executed if the call is
		 * successful after the notification rendering is done.
		 * @param {function} errorCallback the callback that will be executed if the call fails
		 */
		fetchDescendentV2: function(url, successCallback, errorCallback) {
			var self = this;
			var request = $.ajax({
				url: url,
				type: 'GET'
			}).done(function(result, textStatus, jqxhr) {
					// Fill Array
					$.each(result.ocs.data.notifications, function(index) {
						var n = new self.Notif(result.ocs.data.notifications[index]);
						self.notifications[n.getId()] = n;
						self.appendToUI(n);
					});
					if (typeof result.ocs.data.next !== 'undefined') {
						self.addShowMoreNotificationsButton(result.ocs.data.next);
					}
					// Check if we have any, and notify the UI
					if (self.numNotifications() !== 0) {
						self._onHaveNotifications();
					} else {
						self._onHaveNoNotifications();
					}
			}).fail(function() {
				_.bind(self._onFetchError, self)
			});

			if (successCallback) {
				request.done(successCallback);
			}

			if (errorCallback) {
				request.fail(errorCallback);
			}
		},

		/**
		 * Make a GET request to the target url. Consider the result of the request as a list of
		 * notifications sorted from newer to older and draw those notifications accordingly in the UI.
		 * @param {string} url the url of the request, it should be apps/notifications/api/v2/notifications?fetch=asc
		 * with additional parameters in the url
		 * @param {function} successCallback the callback that will be executed if the call is
		 * successful after the notification rendering is done.
		 * @param {function} errorCallback the callback that will be executed if the call fails
		 */
		fetchAscendentV2: function(url, successCallback, errorCallback) {
			var self = this;
			var request = $.ajax({
				url: url,
				type: 'GET'
			}).done(function(result, textStatus, jqxhr){
					// Fill Array
					$.each(result.ocs.data.notifications, function(index) {
						var n = new self.Notif(result.ocs.data.notifications[index]);
						self.notifications[n.getId()] = n;
						self.addToUI(n);
					});
					if (typeof result.ocs.data.next !== 'undefined') {
						self.addShowNewerNotificationsButton(result.ocs.data.next);
					}
					// Check if we have any, and notify the UI
					if (self.numNotifications() !== 0) {
						self._onHaveNotifications();
					} else {
						self._onHaveNoNotifications();
					}
			}).fail(function() {
				_.bind(self._onFetchError, self)
			});

			if (successCallback) {
				request.done(successCallback);
			}

			if (errorCallback) {
				request.fail(errorCallback);
			}
		},

		/**
		 * Make a request to the polling endpoint and update the last notification id. If it's properly
		 * updated the polling will stop
		 */
		pollingV2: function() {
			var self = this;
			var request = $.ajax({
				url: OC.generateUrl('apps/notifications/api/v2/tracker/notifications/polling?format=json'),
				type: 'GET'
			}).done(function(result, textStatus, jqxhr){
				var lastNotificationId = jqxhr.getResponseHeader('OC-Last-Notification');
				if (self.updateLastKnowId(lastNotificationId)) {
					clearInterval(self.interval);
				}
			});
		},

		/**
		 * Update the last known notification id. If the new id is greater than the old one, make
		 * the bell icon flicker and show a button to fetch the newer notifications. Also show a
		 * browser notification if possible.
		 * @return true if the value is updated, false otherwise
		 */
		updateLastKnowId: function(newId) {
			var previousId = this.lastKnownId;
			this.lastKnownId = newId;
			if (previousId !== null && parseInt(previousId, 10) < parseInt(newId, 10)) {
				this._onHaveNotifications();
				this._onLastKnownIdChange();
				this.popupWebBrowserNotification();
				return true;
			}
			return false;
		},

		/**
		 * Update the last shown notification id to keep track of what notifications we should request
		 * later. Only update if the new id is greater than the old one.
		 * @param {string} newId the newest id shown to the user.
		 */
		updateLastShownId: function(newId) {
			var newIdInt = parseInt(newId, 10);
			var lastShownId = this.$container.data('lastShownId');
			if (lastShownId === undefined || newIdInt > lastShownId) {
				this.$container.data('lastShownId', newIdInt);
			}
		},

		/**
		 * Get the last shown id value stored with the "updateLastShownId" method
		 * @return {string|undefined} the stored value, or undefined if no value has been stored yet
		 */
		getLastShownId: function() {
			return this.$container.data('lastShownId');
		},

		_onLastKnownIdChange: function() {
			var lastShownId = this.$container.find('div.notification-wrapper .notification:first').data('id');
			if (lastShownId === undefined) {
				// if we don't know the id shown, rely on the stored one
				lastShownId = this.getLastShownId();
			}
			var targetUrl = OC.generateUrl('apps/notifications/api/v2/notifications?id=' + lastShownId + '&fetch=asc&limit=3&format=json');
			this.addShowNewerNotificationsButton(targetUrl);
		},

		/**
		 * Handles errors when requesting the notifications
		 * @param {XMLHttpRequest} xhr
		 */
		_onFetchError: function(xhr) {
			if (xhr.status === 404) {
				// 404 Not Found - stop polling
				this._shutDownNotifications();
			} else {
				OC.Notification.showTemporary('Failed to request notifications. Please try to refresh the page manually.');
			}
		},

		/**
		 * Show a browser notification to notify the user about new notifications
		 */
		popupWebBrowserNotification: function() {
			if ('Notification' in window && Notification.permission === 'granted') {
				var self = this;
				var title = t('notifications', 'Notifications available on {server}', {server: location.host});
				var body = t('notifications', 'You have new notifications available. Go and check them!');
				var notif = new Notification(title, {
					body: body,
					lang: OC.getLocale(),
					requireInteraction: true
				});
			}
		},

		_shutDownNotifications: function() {
			// The app was disabled or has no notifiers, so we can stop polling
			// And hide the UI as well
			window.clearInterval(this.interval);
		},

		/**
		 * Prepend the notification in the UI container
		 * @param {OCA.Notifications.Notification} notification
		 */
		addToUI: function(notification) {
			this.$container.find('div.notification-wrapper').prepend(notification.renderElement());
			this.updateLastShownId(notification.getId());
		},

		/**
		 * Append the notification in the UI container
		 * @param {OCA.Notifications.Notification} notification
		 */
		appendToUI: function(notification) {
			this.$container.find('div.notification-wrapper').append(notification.renderElement());
		},

		/**
		 * Add a button to the end of the notification list to fetch the next bunch of notifications.
		 * @param {string} nextUrl the url that will be used for a "fetchDescendentV2" call, that
		 * will be called when the button is clicked. The url should normally be the "next" url
		 * from a result of a previous "fetchDescendentV2" call.
		 */
		addShowMoreNotificationsButton: function(nextUrl) {
			var self = this;
			var $notificationControlsDiv = $('<div>', {
				'class' : 'notification-controls controls-below'
			});

			var $showMoreButton = $('<button>', {
				'class' : 'notification-showmore',
				'text' : t('notifications', 'Show More')
			});

			$showMoreButton.click(function() {
				self.removeShowMoreNotificationsButton();
				self.fetchDescendentV2(nextUrl);
			});

			$notificationControlsDiv.append($showMoreButton);

			this.$container.find('div.notification-wrapper').append($notificationControlsDiv);
		},

		/**
		 * Remove the button created with the "addShowMoreNotificationsButton"
		 */
		removeShowMoreNotificationsButton: function() {
			this.$container.find('div.notification-controls.controls-below').remove();
		},

		/**
		 * Add a button to the beginning of the notification list to fetch the newer bunch of
		 * notifications. Once the user click in the button, polling will be restarted if the
		 * "fetchAscendentV2" call doesn't return the link to the next call (there aren't any
		 * additional notification available for now)
		 * @param {string} nextUrl the url that will be used for a "fetchAscendentV2" call, that
		 * will be called when the button is clicked. The url should normally be the "next" url
		 * from a result of a previous "fetchAscendentV2" call.
		 */
		addShowNewerNotificationsButton: function(nextUrl) {
			var self = this;
			var $notificationControlsDiv = $('<div>', {
				'class' : 'notification-controls controls-above'
			});
			var $showMoreButton = $('<button>', {
				'class' : 'notification-shownewer',
				'text' : t('notifications', 'Show Newer Notifications')
			});
			$showMoreButton.click(function() {
				self.removeShowNewerNotificationButton();
				self.fetchAscendentV2(nextUrl, function(result) {
					if (typeof result.next === 'undefined') {
						self.restartPolling();
					}
				});
			});

			$notificationControlsDiv.append($showMoreButton);

			this.$container.find('div.notification-wrapper').prepend($notificationControlsDiv);
		},

		/**
		 * Remove the button created by "addShowNewerNotificationsButton"
		 */
		removeShowNewerNotificationButton: function() {
			this.$container.find('div.notification-controls.controls-above').remove();
		},

		/**
		 * Handle event when we have notifications (and didnt before)
		 */
		_onHaveNotifications: function() {
			// Add the button, title, etc
			this.$button.addClass('hasNotifications');
			this.$button.find('img').attr('src', OC.imagePath('notifications', 'notifications-new'))
				.animate({opacity: 0.5}, 600)
				.animate({opacity: 1}, 600)
				.animate({opacity: 0.5}, 600)
				.animate({opacity: 1}, 600)
				.animate({opacity: 0.7}, 600);
			this.$container.find('.emptycontent').addClass('hidden');
		},

		/**
		 * Handle when all dismissed
		 */
		_onHaveNoNotifications: function() {
			// Remove the border
			$('div.notifications-button').removeClass('hasNotifications');
			$('div.notifications .emptycontent').removeClass('hidden');
			this.$button.find('img').attr('src', OC.imagePath('notifications', 'notifications'));
		},

		/**
		 * Retrieves a notification object by id
		 * @param {int} id
		 */
		getNotification: function(id) {
			if(OCA.Notifications.notifications[id] != undefined) {
				return OCA.Notifications.notifications[id];
			} else {
				return false;
			}
		},

		/**
		 * Returns all notification objects
		 */
		getNotifications: function() {
			return this.notifications;
		},

		/**
		 * Returns how many notifications in the UI
		 */
		numNotifications: function() {
			return _.keys(this.notifications).length;
		}

	};
})();

$(document).ready(function () {
	OCA.Notifications.initialise();
});
