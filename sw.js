// sw.js - Background listener for push notifications
self.addEventListener('push', function(event) {
    let payload = { title: "IT'S YOUR TURN!", body: "Please proceed to the counter immediately." };
    
    if (event.data) {
        try {
            payload = event.data.json();
        } catch (e) {
            payload.body = event.data.text();
        }
    }

    const options = {
        body: payload.body,
        icon: 'https://cdn-icons-png.flaticon.com/512/3144/3144456.png', // Coffee/Ticket placeholder icon
        badge: 'https://cdn-icons-png.flaticon.com/512/3144/3144456.png',
        vibrate: [300, 100, 300, 100, 400],
        data: { dateOfArrival: Date.now() },
        actions: [
            { action: 'open', title: 'View Status' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(payload.title, options)
    );
});

// Handle when the user clicks on the notification pop-up
self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    event.waitUntil(
        clients.openWindow('/') // Opens or focuses back onto your application layout
    );
});