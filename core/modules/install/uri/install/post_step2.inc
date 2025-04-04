<?php

load_library('get');
load_library('set');
load_library('data');
load_library('encrypt');
load_library('salt');

if (post_get('submit', false) === 'Next') {
  run_library('set', array('step' => '3', 'overwrite' => true));
}

run_library('sticky-post');

$alias = get_sc('sticky.rewritebase');
if (!empty($alias)) {
  run_library('set', array('rewritebase-slash' => $alias . '/',  'overwrite' => true));
} else {
  run_library('set', array('rewritebase-slash' => '', 'overwrite' => true));
}

/*
 * Create htaccess file
 */

$htaccess_content = run_buffered(realpath(dirname(__FILE__)) . '/root.htaccess.tpl');
$htaccess_file = $GLOBALS['SYSTEM']['file_base'] . '.htaccess';
if (!file_exists($htaccess_file)) {
  if (false === file_put_contents($htaccess_file, $htaccess_content)) {
    set_variable('htaccess_ok', 'fail');
  }
}

if (file_exists($htaccess_file)) {
  chmod($htaccess_file, 0640);
  set_variable('htaccess_ok', 'ok');
}

/*
 * Create theme files
 */

if (!file_exists($GLOBALS['SYSTEM']['file_base'] . '/ext/tailwind.theme.js')) {
  copy(realpath(dirname(__FILE__)) . '/tailwind.theme.js', $GLOBALS['SYSTEM']['file_base'] . '/ext/tailwind.theme.js');
}
if (!file_exists($GLOBALS['SYSTEM']['file_base'] . '/ext/theme.css')) {
  touch($GLOBALS['SYSTEM']['file_base'] . '/ext/theme.css');
}

/* 
 * Persist site name in .config resource
 */

$sitename = post_get('sitename');
set_variable('site-name', $sitename);
if (data_exists('.config', 'site')) {
  $ok = true;
} else {
  $ok = data_create('.config', 'site', [
    'name' => $sitename,
    'description' => $sitename . ': a Nimbly site'
  ]);
}

if ($ok) {
  set_variable('sitename_ok', 'ok');
} else {
  set_variable('sitename_ok', 'fail');
}

/*
 * Create .content resource
 */

if (!data_exists('.content', '.meta')) {
  data_create_resource(".content", ["fields" => false]);
}


/*
 * Create readme file
 */

$readme_content = run_buffered(realpath(dirname(__FILE__)) . '/readme.md.tpl');
$readme_file = $GLOBALS['SYSTEM']['file_base'] . 'ext/readme.md';
if (!file_exists($readme_file)) {
  if (false === file_put_contents($readme_file, $readme_content)) {
    set_variable('readme_ok', 'fail');
  }
}

if (file_exists($readme_file)) {
  chmod($readme_file, 0640);
  set_variable('readme_ok', 'ok');
}

/*
 * Create .gitignore file
 */

$gitignore_content = run_buffered(realpath(dirname(__FILE__)) . '/.gitignore.tpl');
$gitignore_file = $GLOBALS['SYSTEM']['file_base'] . 'ext/.gitignore';
if (!file_exists($gitignore_file)) {
  if (false === file_put_contents($gitignore_file, $gitignore_content)) {
    set_variable('gitignore_ok', 'fail');
  }
}

if (file_exists($gitignore_file)) {
  chmod($gitignore_file, 0640);
  set_variable('gitignore_ok', 'ok');
}

/*
 * Create core routes
 */

$routes = [
  ['route' => 'api', 'order' => 900],
  ['route' => 'api/v1/(resource)', 'order' => 500],
  ['route' => 'api/v1/(resource)/(id)', 'order' => 200],
  ['route' => 'api/v1/(resource)/(id)/(id)', 'order' => 200],
  ['route' => 'api/v1/.pages/(id)', 'order' => 200],
  ['route' => 'api/v1/.files/(id)', 'order' => 200],
  ['route' => 'nb-admin/(resource)', 'order' => 400],
  ['route' => 'nb-admin/(resource)/(id)', 'order' => 500],
  ['route' => 'nb-admin/(resource)/add', 'order' => 300],
  ['route' => 'nb-admin/pages/(id)', 'order' => 200],
  ['route' => 'nb-admin/files/(id)', 'order' => 200],
  ['route' => 'img/(id)', 'order' => 500],
  ['route' => 'download/(id)', 'order' => 500],
  ['route' => 'video/(id)', 'order' => 500],
  ['route' => 'password-reset/(uuid)/(key)', 'order' => 200],
  ['route' => 'change-email/(uuid)/(newuuid)/(key)', 'order' => 200]
];

foreach ($routes as $route) {
  $uuid = md5($route['route']);
  if (data_exists('.routes', $uuid)) {
    continue;
  }
  data_create('.routes', $uuid, $route);
}

/*
 * Create roles
 */

if (!data_exists('roles', 'admin')) {
  data_create('roles', 'admin', [
    'name' => 'Admin',
    'description' => 'Technical system administration ',
    'features' => '(all)'
  ]);
}

if (!data_exists('roles', 'editor')) {

  data_create('roles', 'editor', [
    'name' => 'Editor',
    'description' => 'Content writers, site maintainers',
    'features' => 'manage-content'
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
        'required' => true,
        'name' => 'email'
      ],
      'password' => [
        'type' => 'password',
        'required' => true,
        'name' => 'password',
        'admin_col' => false,
      ],
      'roles' => [
        'type' => 'select',
        'multi' => true,
        'name' => 'roles',
        'resource' => 'roles'
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

if (!data_exists('users', md5($email))) {
  $salt = salt_sc();
  $email = post_get('email');
  $pw = post_get('password');
  if (false !== data_create('users', md5($email), array(
    'email' => $email,
    'roles' => 'admin,user',
    'salt' => $salt,
    'password' => encrypt($pw, $salt)
  ))) {
    set_variable('user_ok', 'ok');
  } else {
    set_variable('user_ok', 'fail');
  }
}