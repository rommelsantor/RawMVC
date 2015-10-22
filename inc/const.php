<?php namespace RawMVC;

ini_set('display_errors', 1);
error_reporting(E_ALL & ~(E_NOTICE));

define('APP_TITLE', 'My RawMVC App');
define('APP_URL', '/');

define('APP_DIR', realpath(dirname(__FILE__) . '/..') . '/');

define('INC_DIR', APP_DIR . 'inc/');
define('APP_FOLDER', basename(INC_DIR));
define('APP_MAIN_FILE', APP_FOLDER . '/' . basename(__FILE__));

define('IMAGE_DIR', APP_DIR . 'images/');
define('IMAGE_FOLDER', 'images');
define('IMAGE_URL', APP_URL . IMAGE_FOLDER . '/');

define('TEMPLATE_FOLDER', 'tpl');
define('TEMPLATE_DIR', APP_DIR . TEMPLATE_FOLDER . '/');
define('TEMPLATE_URL', APP_URL . TEMPLATE_FOLDER . '/');

/**
 * in ./db-config.php define four constants as follows:
 *
 * define('DB_HOST', 'localhost');
 * define('DB_NAME', 'mydbname');
 * define('DB_USER', 'myusername');
 * define('DB_PASSWORD', 'mypassword');
 */
if (!is_file(APP_DIR . 'db-config.php'))
  trigger_error(APP_DIR . 'db-config.php must define your DB connection constants', E_USER_ERROR);

require_once(APP_DIR . 'db-config.php');

