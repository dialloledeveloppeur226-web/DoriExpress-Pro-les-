/**
 * =============================================
 * SERVICE WORKER - DoriExpress-Pro
 * =============================================
 * Fichier : pwa/service-worker.js
 * Rôle : Gestion du cache, mode hors ligne, notifications push
 * Niveau : Uber / Glovo / Deliveroo
 * =============================================
 */

// =============================================
// 1. CONSTANTES
// =============================================
const CACHE_VERSION = 'v1';
const CACHE_NAME = `doriexpress-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline.php';

// Fichiers à mettre en cache
const STATIC_ASSETS = [
  '/',
  '/index.php',
  '/offline.php',
  '/assets/css/style.css',
  '/assets/css/responsive.css',
  '/assets/css/animations.css',
  '/assets/css/dark-mode.css',
  '/assets/js/app.js',
  '/assets/js/dashboard.js',
  '/assets/js/maps.js',
  '/assets/js/chat.js',
  '/assets/js/paiement.js',
  '/assets/js/notifications.js',
  '/assets/js/search.js',
  '/assets/js/validation.js',
  '/assets/js/darkmode.js',
  '/assets/images/logo-192x192.png',
  '/assets/images/logo-512x512.png',
  '/assets/images/favicon.ico',
  '/assets/icons/icon-72x72.png',
  '/assets/icons/icon-96x96.png',
  '/assets/icons/icon-128x128.png',
  '/assets/icons/icon-144x144.png',
  '/assets/icons/icon-152x152.png',
  '/assets/icons/icon-192x192.png',
  '/assets/icons/icon-384x384.png',
  '/assets/icons/icon-512x512.png',
  '/assets/fonts/inter.woff2',
  '/pwa/manifest.json'
];

// Pages statiques à mettre en cache
const STATIC_PAGES = [
  '/',
  '/index.php',
  '/services.php',
  '/tarifs.php',
  '/faq.php',
  '/contact.php',
  '/blog.php',
  '/partenaires.php',
  '/promotions.php',
  '/actualites.php'
];

// API à mettre en cache (GET)
const CACHED_API = [
  '/api/notifications/count.php',
  '/api/statistiques/quick.php'
];

// =============================================
// 2. INSTALLATION
// =============================================
self.addEventListener('install', function(event) {
  console.log('[ServiceWorker] Installation');

  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('[ServiceWorker] Mise en cache des assets statiques');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(function() {
        console.log('[ServiceWorker] Cache prêt');
        return self.skipWaiting();
      })
      .catch(function(error) {
        console.error('[ServiceWorker] Erreur de cache:', error);
      })
  );
});

// =============================================
// 3. ACTIVATION
// =============================================
self.addEventListener('activate', function(event) {
  console.log('[ServiceWorker] Activation');

  event.waitUntil(
    caches.keys()
      .then(function(cacheNames) {
        return Promise.all(
          cacheNames.map(function(cacheName) {
            if (cacheName !== CACHE_NAME && cacheName.startsWith('doriexpress-')) {
              console.log('[ServiceWorker] Suppression de l\'ancien cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(function() {
        console.log('[ServiceWorker] Claiming clients');
        return self.clients.claim();
      })
  );
});

// =============================================
// 4. INTERCEPTION DES REQUÊTES
// =============================================
self.addEventListener('fetch', function(event) {
  const request = event.request;
  const url = new URL(request.url);

  // Ignorer les requêtes non-GET
  if (request.method !== 'GET') {
    return;
  }

  // Stratégie de cache différente selon le type
  let strategy = 'network-first';

  // Assets statiques - Cache First
  if (STATIC_ASSETS.some(asset => url.pathname.includes(asset))) {
    strategy = 'cache-first';
  }

  // Pages statiques - Network First avec fallback
  if (STATIC_PAGES.some(page => url.pathname === page || url.pathname === page.replace('.php', ''))) {
    strategy = 'network-first';
  }

  // API - Network Only
  if (url.pathname.startsWith('/api/')) {
    strategy = 'network-only';
  }

  // Images - Cache First
  if (url.pathname.match(/\.(png|jpg|jpeg|gif|webp|svg)$/)) {
    strategy = 'cache-first';
  }

  // Appliquer la stratégie
  event.respondWith(
    handleRequest(request, strategy)
  );
});

// =============================================
// 5. GESTION DES REQUÊTES
// =============================================
function handleRequest(request, strategy) {
  switch (strategy) {
    case 'cache-first':
      return cacheFirst(request);
    case 'network-first':
      return networkFirst(request);
    case 'network-only':
      return networkOnly(request);
    default:
      return networkFirst(request);
  }
}

// =============================================
// 6. STRATÉGIE CACHE-FIRST
// =============================================
function cacheFirst(request) {
  return caches.open(CACHE_NAME)
    .then(function(cache) {
      return cache.match(request)
        .then(function(response) {
          if (response) {
            return response;
          }
          return fetch(request)
            .then(function(networkResponse) {
              cache.put(request, networkResponse.clone());
              return networkResponse;
            });
        });
    })
    .catch(function() {
      return caches.match('/offline.php');
    });
}

// =============================================
// 7. STRATÉGIE NETWORK-FIRST
// =============================================
function networkFirst(request) {
  return fetch(request)
    .then(function(response) {
      // Mettre en cache la réponse
      const responseClone = response.clone();
      caches.open(CACHE_NAME)
        .then(function(cache) {
          cache.put(request, responseClone);
        });
      return response;
    })
    .catch(function() {
      return caches.match(request)
        .then(function(response) {
          if (response) {
            return response;
          }
          return caches.match('/offline.php');
        });
    });
}

// =============================================
// 8. STRATÉGIE NETWORK-ONLY
// =============================================
function networkOnly(request) {
  return fetch(request)
    .catch(function() {
      return new Response(
        JSON.stringify({ error: 'Hors ligne', success: false }),
        {
          status: 503,
          headers: { 'Content-Type': 'application/json' }
        }
      );
    });
}

// =============================================
// 9. GESTION DES PUSH NOTIFICATIONS
// =============================================
self.addEventListener('push', function(event) {
  console.log('[ServiceWorker] Push reçue');

  let data = {
    title: 'DoriExpress-Pro',
    body: 'Vous avez une nouvelle notification',
    icon: '/assets/images/logo-192x192.png',
    badge: '/assets/icons/icon-72x72.png',
    url: '/notifications.php',
    tag: 'notification',
    requireInteraction: false
  };

  if (event.data) {
    try {
      const parsedData = event.data.json();
      data = { ...data, ...parsedData };
    } catch (e) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon,
    badge: data.badge,
    data: {
      url: data.url,
      id: data.id
    },
    tag: data.tag,
    vibrate: [200, 100, 200],
    actions: data.actions || [
      {
        action: 'open',
        title: 'Voir'
      },
      {
        action: 'close',
        title: 'Fermer'
      }
    ],
    requireInteraction: data.requireInteraction || false,
    silent: data.silent || false
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// =============================================
// 10. CLIC SUR NOTIFICATION
// =============================================
self.addEventListener('notificationclick', function(event) {
  console.log('[ServiceWorker] Clic sur notification');

  event.notification.close();

  const urlToOpen = event.notification.data?.url || '/notifications.php';

  event.waitUntil(
    clients.matchAll({
      type: 'window',
      includeUncontrolled: true
    })
    .then(function(windowClients) {
      // Ouvrir dans un onglet existant si possible
      for (let i = 0; i < windowClients.length; i++) {
        const client = windowClients[i];
        if (client.url === urlToOpen && 'focus' in client) {
          return client.focus();
        }
      }
      // Sinon ouvrir un nouvel onglet
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});

// =============================================
// 11. GESTION DES MESSAGES (POSTMESSAGE)
// =============================================
self.addEventListener('message', function(event) {
  const data = event.data;

  if (data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }

  if (data.type === 'CLEAR_CACHE') {
    caches.delete(CACHE_NAME);
    event.ports[0].postMessage({ success: true });
  }

  if (data.type === 'GET_CACHE_SIZE') {
    caches.open(CACHE_NAME)
      .then(function(cache) {
        return cache.keys();
      })
      .then(function(keys) {
        event.ports[0].postMessage({ size: keys.length });
      });
  }
});

// =============================================
// 12. SYNC BACKGROUND
// =============================================
self.addEventListener('sync', function(event) {
  console.log('[ServiceWorker] Sync en arrière-plan:', event.tag);

  if (event.tag === 'sync-commandes') {
    event.waitUntil(syncCommandes());
  }

  if (event.tag === 'sync-notifications') {
    event.waitUntil(syncNotifications());
  }
});

function syncCommandes() {
  return fetch('/api/sync/commandes.php')
    .then(function(response) {
      return response.json();
    })
    .then(function(data) {
      console.log('[ServiceWorker] Sync commandes terminée:', data);
    })
    .catch(function(error) {
      console.error('[ServiceWorker] Erreur sync commandes:', error);
    });
}

function syncNotifications() {
  return fetch('/api/sync/notifications.php')
    .then(function(response) {
      return response.json();
    })
    .then(function(data) {
      console.log('[ServiceWorker] Sync notifications terminée:', data);
    })
    .catch(function(error) {
      console.error('[ServiceWorker] Erreur sync notifications:', error);
    });
}

// =============================================
// 13. GESTION DU RÉSEAU (ONLINE/OFFLINE)
// =============================================
self.addEventListener('online', function() {
  console.log('[ServiceWorker] Connexion rétablie');
  // Notifier les clients
  self.clients.matchAll().then(function(clients) {
    clients.forEach(function(client) {
      client.postMessage({
        type: 'network_status',
        status: 'online'
      });
    });
  });
});

self.addEventListener('offline', function() {
  console.log('[ServiceWorker] Connexion perdue');
  self.clients.matchAll().then(function(clients) {
    clients.forEach(function(client) {
      client.postMessage({
        type: 'network_status',
        status: 'offline'
      });
    });
  });
});

// =============================================
// 14. MISE À JOUR AUTOMATIQUE
// =============================================
self.addEventListener('controllerchange', function() {
  console.log('[ServiceWorker] Contrôleur changé');
  // Notifier les clients qu'une mise à jour est disponible
  self.clients.matchAll().then(function(clients) {
    clients.forEach(function(client) {
      client.postMessage({
        type: 'update_available'
      });
    });
  });
});

// =============================================
// 15. LOG
// =============================================
console.log('[ServiceWorker] Service Worker chargé avec succès');

// =============================================
// FIN DU SERVICE WORKER
// =============================================