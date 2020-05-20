<?php

load_libraries(['get', 'set', 'data', 'encrypt', 'salt']);

/* 
 * Persist site name
 */

$sitename = post_get('sitename');
if (!data_exists('.config', 'site')) {
    data_create('.config', 'site', [
        'name' => $sitename,
        'description' => $sitename . ': a nimbly site',
        'keywords' => $sitename . ', nimbly',
        'login_url' => 'admin'
  ]);
} else {
    data_update('.config', 'site', ['name' => $sitename]);
}

/*
 * Create readme file
 */

$readme_file = $GLOBALS['SYSTEM']['file_base'] . 'ext/readme.md';
if (!file_exists($readme_file)) {
  set_variable('site-name', $sitename);
  $readme_content = run_buffered(realpath(dirname(__FILE__)) . '/readme.md.tpl');
  if (file_put_contents($readme_file, $readme_content)) {
    chmod($readme_file, 0640);
  } 
}

/*
 * Create .gitignore file
 */

$gitignore_file = $GLOBALS['SYSTEM']['file_base'] . 'ext/.gitignore';
if (!file_exists($gitignore_file)) {
    $gitignore_content = run_buffered(realpath(dirname(__FILE__)) . '/.gitignore.tpl');
    if (file_put_contents($gitignore_file, $gitignore_content)) {
        chmod($gitignore_file, 0640);
    }
}

/*
 * Create core routes
 */

if (!data_exists('.routes', '.meta')) {
    data_create('.routes', '.meta', [
        'fields' => [
          'route' => [
            'type' => 'text',
            'name' => 'Route'
          ],
          'order' => [
            'type' => 'text',
            'name' => 'Order'
          ]
        ],
        'pk' => 'route'
    ]);
}

$routes = [
    ['route' => 'api', 'order' => 900],
    ['route' => 'api/v1/(resource)', 'order' => 500],
    ['route' => 'api/v1/(resource)/(id)', 'order' => 200],
    ['route' => 'api/v1/(resource)/(id)/(id)', 'order' => 200],
    ['route' => 'api/v1/.pages/(id)', 'order' => 200],
    ['route' => 'api/v1/.files/(id)', 'order' => 200],
    ['route' => 'admin/(resource)', 'order' => 400],
    ['route' => 'admin/(resource)/(id)', 'order' => 500],
    ['route' => 'admin/(resource)/add', 'order' => 300],
    ['route' => 'admin/pages/(id)', 'order' => 200],
    ['route' => 'admin/files/(id)', 'order' => 200],
    ['route' => 'img/(id)', 'order' => 500],
    ['route' => 'password-reset/(uuid)/(key)', 'order' => 200],
    ['route' => 'change-email/(uuid)/(newuuid)/(key)', 'order' => 200]
];

foreach ($routes as $route) {
    $key = md5($route['route']);
    if (!data_exists('.routes', $key)) {
      data_create('.routes', $key, $route);
    }
}

/*
 * Create admin role
 */

if (!data_exists('roles', 'admin')) {
  data_create('roles', 'admin', [
      'name' => 'Admin',
      'description' => 'For technical administration of [site-name]',
      'features' => '(all)'
  ]);
}

/*
 * Create users resource
 */

 if (!data_exists('users', '.meta')) {
    data_create('users', '.meta', [
      'fields' => [
            'email' => [
              'type' => 'text',
              'name' => 'email'
            ],
            'password' => [
              'type' => 'password',
              'name' => 'password'
            ],
            'roles' => [
              'type' => 'text',
              'name' => 'roles'
            ],
            'name' => [
              'type' => 'text',
              'name' => 'name'
            ]
      ],
      'pk' => 'email',
      'encrypt' => 'password'
    ]);
}

/*
 * Create admin user
 */

$salt = salt_sc();
$email = post_get('email');
$pw = post_get('password');
data_create('users', md5($email), [
    'email' => $email,
    'roles' => 'admin,user',
    'salt' => $salt,
    'password' => encrypt($pw, $salt)
]);

/*
 * Create htaccess file
 */

 
$pepper = post_get('pepper');
set_variable('pepper', $pepper);
$htaccess_content = run_buffered(realpath(dirname(__FILE__)) . '/root.htaccess.tpl');
$htaccess_file = $GLOBALS['SYSTEM']['file_base'] . '.htaccess';
if (file_put_contents($htaccess_file, $htaccess_content)) {
    chmod($htaccess_file, 0640);
}

load_library('redirect');
redirect('login');