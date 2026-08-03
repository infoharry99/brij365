importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: "AIzaSyA9oKk0cFtqyfxaVqHDMrl644dDlaka1LU",
  projectId: "brijchat-6d93f",
  messagingSenderId: "589664366975",
  appId: "1:589664366975:web:builder360"
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
  console.log('[FCM Web Worker] Background message received:', payload);
  const title = payload.notification?.title || payload.data?.title || 'New Chat Message';
  const options = {
    body: payload.notification?.body || payload.data?.body || '',
    icon: '/favicon.ico',
    badge: '/favicon.ico',
    data: payload.data || {},
  };

  return self.registration.showNotification(title, options);
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const conversationId = event.notification.data?.conversation_id;
  const targetUrl = conversationId
    ? '/collaboration/chat?conversation_id=' + conversationId
    : '/collaboration/chat';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      for (let client of windowClients) {
        if (client.url.includes('/collaboration/chat') && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
