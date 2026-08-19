<?php

// Auto-generated Route Cache - DO NOT EDIT MANUALLY
// Generated at: 2026-08-19 00:00:55

return array (
  'generated_at' => '2026-08-19T00:00:55+00:00',
  'middleware_groups' => 
  array (
    'auth' => 
    array (
      0 => 'App\\Middleware\\AuthMiddleware',
    ),
    'api.secure' => 
    array (
      0 => 'App\\Middleware\\AuthMiddleware',
      1 => 'App\\Middleware\\RateLimitMiddleware',
    ),
    'cors' => 
    array (
      0 => 'App\\Middleware\\CorsMiddleware',
    ),
    'global' => 
    array (
      0 => 'App\\Middleware\\CorsMiddleware',
      1 => 'App\\Middleware\\SecurityHeadersMiddleware',
    ),
    'csrf' => 
    array (
      0 => 'App\\Middleware\\CsrfMiddleware',
    ),
    'api.key' => 
    array (
      0 => 'App\\Middleware\\EnsureApiKeyMiddleware',
    ),
    'api' => 
    array (
      0 => 'App\\Middleware\\EnsureApiKeyMiddleware',
    ),
    'throttle' => 
    array (
      0 => 'App\\Middleware\\RateLimitMiddleware',
    ),
    'security-headers' => 
    array (
      0 => 'App\\Middleware\\SecurityHeadersMiddleware',
    ),
  ),
  'router' => 
  array (
    'routes' => 
    array (
      0 => 
      array (
        'method' => 'GET',
        'path' => '/products',
        'handler' => 
        array (
          0 => 'App\\Controller\\ProductController',
          1 => 'index',
        ),
        'middleware' => 
        array (
        ),
      ),
      1 => 
      array (
        'method' => 'GET',
        'path' => '/products/{id}',
        'handler' => 
        array (
          0 => 'App\\Controller\\ProductController',
          1 => 'show',
        ),
        'middleware' => 
        array (
        ),
        'regex' => '#^/products/([a-zA-Z0-9_\\-]+)$#',
      ),
      2 => 
      array (
        'method' => 'POST',
        'path' => '/products/store',
        'handler' => 
        array (
          0 => 'App\\Controller\\ProductController',
          1 => 'store',
        ),
        'middleware' => 
        array (
        ),
      ),
      3 => 
      array (
        'method' => 'PUT',
        'path' => '/products/{id}',
        'handler' => 
        array (
          0 => 'App\\Controller\\ProductController',
          1 => 'update',
        ),
        'middleware' => 
        array (
        ),
        'regex' => '#^/products/([a-zA-Z0-9_\\-]+)$#',
      ),
      4 => 
      array (
        'method' => 'DELETE',
        'path' => '/products/{id}',
        'handler' => 
        array (
          0 => 'App\\Controller\\ProductController',
          1 => 'destroy',
        ),
        'middleware' => 
        array (
        ),
        'regex' => '#^/products/([a-zA-Z0-9_\\-]+)$#',
      ),
      5 => 
      array (
        'method' => 'GET',
        'path' => '/users',
        'handler' => 
        array (
          0 => 'App\\Controller\\UserController',
          1 => 'index',
        ),
        'middleware' => 
        array (
        ),
      ),
      6 => 
      array (
        'method' => 'GET',
        'path' => '/users/{id}',
        'handler' => 
        array (
          0 => 'App\\Controller\\UserController',
          1 => 'show',
        ),
        'middleware' => 
        array (
        ),
        'regex' => '#^/users/([a-zA-Z0-9_\\-]+)$#',
      ),
      7 => 
      array (
        'method' => 'POST',
        'path' => '/users/store',
        'handler' => 
        array (
          0 => 'App\\Controller\\UserController',
          1 => 'store',
        ),
        'middleware' => 
        array (
        ),
      ),
      8 => 
      array (
        'method' => 'PUT',
        'path' => '/users/{id}',
        'handler' => 
        array (
          0 => 'App\\Controller\\UserController',
          1 => 'update',
        ),
        'middleware' => 
        array (
        ),
        'regex' => '#^/users/([a-zA-Z0-9_\\-]+)$#',
      ),
      9 => 
      array (
        'method' => 'DELETE',
        'path' => '/users/{id}',
        'handler' => 
        array (
          0 => 'App\\Controller\\UserController',
          1 => 'destroy',
        ),
        'middleware' => 
        array (
        ),
        'regex' => '#^/users/([a-zA-Z0-9_\\-]+)$#',
      ),
    ),
    'static_routes' => 
    array (
      'GET' => 
      array (
        '/products' => 
        array (
          'method' => 'GET',
          'path' => '/products',
          'handler' => 
          array (
            0 => 'App\\Controller\\ProductController',
            1 => 'index',
          ),
          'middleware' => 
          array (
          ),
        ),
        '/users' => 
        array (
          'method' => 'GET',
          'path' => '/users',
          'handler' => 
          array (
            0 => 'App\\Controller\\UserController',
            1 => 'index',
          ),
          'middleware' => 
          array (
          ),
        ),
      ),
      'POST' => 
      array (
        '/products/store' => 
        array (
          'method' => 'POST',
          'path' => '/products/store',
          'handler' => 
          array (
            0 => 'App\\Controller\\ProductController',
            1 => 'store',
          ),
          'middleware' => 
          array (
          ),
        ),
        '/users/store' => 
        array (
          'method' => 'POST',
          'path' => '/users/store',
          'handler' => 
          array (
            0 => 'App\\Controller\\UserController',
            1 => 'store',
          ),
          'middleware' => 
          array (
          ),
        ),
      ),
    ),
    'dynamic_routes' => 
    array (
      'GET' => 
      array (
        0 => 
        array (
          'method' => 'GET',
          'path' => '/products/{id}',
          'handler' => 
          array (
            0 => 'App\\Controller\\ProductController',
            1 => 'show',
          ),
          'middleware' => 
          array (
          ),
          'regex' => '#^/products/([a-zA-Z0-9_\\-]+)$#',
        ),
        1 => 
        array (
          'method' => 'GET',
          'path' => '/users/{id}',
          'handler' => 
          array (
            0 => 'App\\Controller\\UserController',
            1 => 'show',
          ),
          'middleware' => 
          array (
          ),
          'regex' => '#^/users/([a-zA-Z0-9_\\-]+)$#',
        ),
      ),
      'PUT' => 
      array (
        0 => 
        array (
          'method' => 'PUT',
          'path' => '/products/{id}',
          'handler' => 
          array (
            0 => 'App\\Controller\\ProductController',
            1 => 'update',
          ),
          'middleware' => 
          array (
          ),
          'regex' => '#^/products/([a-zA-Z0-9_\\-]+)$#',
        ),
        1 => 
        array (
          'method' => 'PUT',
          'path' => '/users/{id}',
          'handler' => 
          array (
            0 => 'App\\Controller\\UserController',
            1 => 'update',
          ),
          'middleware' => 
          array (
          ),
          'regex' => '#^/users/([a-zA-Z0-9_\\-]+)$#',
        ),
      ),
      'DELETE' => 
      array (
        0 => 
        array (
          'method' => 'DELETE',
          'path' => '/products/{id}',
          'handler' => 
          array (
            0 => 'App\\Controller\\ProductController',
            1 => 'destroy',
          ),
          'middleware' => 
          array (
          ),
          'regex' => '#^/products/([a-zA-Z0-9_\\-]+)$#',
        ),
        1 => 
        array (
          'method' => 'DELETE',
          'path' => '/users/{id}',
          'handler' => 
          array (
            0 => 'App\\Controller\\UserController',
            1 => 'destroy',
          ),
          'middleware' => 
          array (
          ),
          'regex' => '#^/users/([a-zA-Z0-9_\\-]+)$#',
        ),
      ),
    ),
  ),
);
