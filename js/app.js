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

    var App = {

        notifications: {},

        num: 0,

        pollInterval: 10000,

        open: false,

        initialise: function() {
            // Go!

            // Setup elements
            var $notifications = $('<div class="notifications"></div>');
            var $button = $('<div class="notifications-button"><img class="svg" alt="Dismiss" src="/core/img/actions/info-white.svg"></div>');
            var $container = $('<div class="notification-container"></div>');
            var $wrapper = $('<div class="notification-wrapper"></div>');
            $notifications.append($button);
            $notifications.append($container);
            $container.append($wrapper);

            // Add to the UI
            $('form.searchbox').before($notifications);

            // Setup the background timer for polling the server

            // Inital call to the notification endpoint
            this.initialFetch();

            // Bind the button click event
            $button.on('click', this._onNotificationsButtonClick);

            // Setup the background checker
            setInterval(this.backgroundFetch, this.pollInterval);
        },

        /**
         * Handles the notification button click event
         */
        _onNotificationsButtonClick: function(e) {
            // Show a popup
            $('div.notification-container').slideToggle(OC.menuSpeed);
        },

        initialFetch: function() {
            this.fetch(
                function(data) {
                    // Fill Array
                    $.each(data, function(index) {
                        var n = new OCA.Notifications.Notif(data[index]);
                        OCA.Notifications.notifications[n.getId()] = n;
                        OCA.Notifications.addToUI(n);
                        OCA.Notifications.num++;
                        // TODO sort by time
                    });
                    // Check if we have any, and notify the UI
                    if(OCA.Notifications.numNotifications() != 0) {
                        OCA.Notifications._onHaveNotifications();
                    } else {
                        OCA.Notifications._onHaveNoNotifications();
                    }
                },
                function(jqXHR) {
                    console.log('Failed to perform initial request for notifications');
                }
            );
        },

        /**
         * Background fetch handler
         */
        backgroundFetch: function() {
            OCA.Notifications.fetch(
                function(data) {
                    var inJson = [];
                    var oldNum = OCA.Notifications.numNotifications();
                    $.each(data, function(index) {
                        var n = new OCA.Notifications.Notif(data[index]);
                        inJson.push(n.getId());
                        if(!OCA.Notifications.getNotification(n.getId())){
                            // New notification!
                            OCA.Notifications._onNewNotification(n);
                        }
                    });
                    // TODO check if any removed from JSON
                    for(var n in OCA.Notifications.getNotifications()) {
                        if(inJson.indexOf(OCA.Notifications.getNotifications()[n].getId()) == -1) {
                            // Not in JSON, remove from UI
                            OCA.Notifications._onRemoveNotification(OCA.Notifications.getNotifications()[n]);
                        }
                    }

                    // Now check if we suddenly have notifs, or now none
                    if(oldNum == 0 && OCA.Notifications.numNotifications() != 0) {
                        // We now have some!
                        OCA.Notifications._onHaveNotifications();
                    } else if(oldNum != 0 && OCA.Notifications.numNotifications() == 0) {
                        // Now we have none
                        OCA.Notifications._onHaveNoNotifications();
                    }
                },
                function(jqXHR) {
                    // Bad
                    console.log('Failed to fetch notifications');
                }
            );
        },

        /**
         * Handles removing the Notification from the UI when no longer in JSON
         */
        _onRemoveNotification: function(n) {
            $('div.notification[data-id='+escapeHTML(n.getId())+']').remove();
            delete OCA.Notifications.notifications[n.getId()];
            OCA.Notifications.num--;
        },

        /**
         * Handle new notification received
         * @param OCA.Notifications.Notification
         */
        _onNewNotification: function(notification) {
            OCA.Notifications.num++;
            // Add it to the array
            OCA.Notifications.notifications[notification.getId()] = notification;
            // Add to the UI
            OCA.Notifications.addToUI(notification);
            // TODO make a noise? Anything else?
        },

        /**
         * Adds the notification to the UI
         */
        addToUI: function(notification) {
            // TODO sort via timestamp
            $('div.notification-wrapper').prepend(notification.renderElement());
        },

        /**
         * Handle event when we have notifications (and didnt before)
         */
        _onHaveNotifications: function() {
            // Add the button, title, etc
            // TODO if still laoding the page, wait until done then flash
            $('div.notifications-button')
            .addClass('hasNotifications')
            .animate({opacity: 0.5})
            .animate({opacity: 1})
            .animate({opacity: 0.5})
            .animate({opacity: 1})
            .animate({opacity: 0.7});
        },

        /**
         * Handle when all dismissed
         */
        _onHaveNoNotifications: function() {
            // Remove the border
            $('div.notifications-button').removeClass('hasNotifications');
        },

        /**
         * Performs the AJAX request to retrieve the notifications
         */
        fetch: function(success, failure){
            var request = $.ajax({
                url: OC.generateUrl('/apps/notifications'),
                type: 'GET'
            });
            request.success(success);
            request.fail(failure);
        },

        /**
         * Retrieves a notification object by id
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
         * Handles the returned data from the AJAX call
         */
        parseNotifications: function(responseData) {

        },

        /**
         * Returns how many notifications in the UI
         */
        numNotifications: function() {
            return OCA.Notifications.num;
        }

    };

    OCA.Notifications = App;

})();

$(document).ready(function () {
    OCA.Notifications.initialise();
});