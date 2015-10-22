<?php namespace RawMVC;

require_once(dirname(__FILE__) . '/const.php');

class Kernel {
  public static $CONTROLLERS = [];

  public static $STYLES = [];
  public static $SCRIPTS = [];

  public static $s_obj;

  /**
   *
   */
  public function __construct() {
    self::$s_obj = $this;

    spl_autoload_register(function($i_name) {
      list($ns, $name) = explode('\\', $i_name, 2);

      if ($ns != __NAMESPACE__)
        return;

      $name = strtolower($name);

      if (substr($name, -5) == 'trait') {
        if (Kernel::load('trait/' . substr($name, 0, -5) . '.php'))
          return;
      }
      else if (substr($name, -4) == 'view') {
        if (Kernel::load('view/' . substr($name, 0, -4) . '.php'))
          return;
      }

      Kernel::load('model/' . $name . '.php');
    });

    self::load('inc/global.php');
    self::load('inc/db.php');
    
    if (PHP_SAPI == 'cli' && @$_SERVER['argv'][1] == 'install') {
      self::install_application();
      die("done.\n");
    }

    self::initialize();

    self::enqueue_std_styles();
    self::enqueue_std_scripts();

    $this->bootstrap();
  }

  /**
   *
   */
  public static function load($i_file) {
    if (!file_exists($path = APP_DIR . $i_file))
      return false;

    @include_once(APP_DIR . $i_file);
    return true;
  }

  /**
   *
   */
  public function install_application() {
    foreach (new \DirectoryIterator(APP_DIR . 'model') as $fi) {
      if ($fi->isDot())
        continue;

      if ($fi->getExtension() != 'php')
        continue;

      $filename = $fi->getFilename();
      $class = ns(substr($filename, 0, -4));
      $trait = ns('ModelTrait');

      if (!self::load('model/' . $filename))
        trigger_error("Model file ($filename) could not be loaded", E_USER_ERROR);

      if (!class_exists($class))
        trigger_error("Model file ($filename) found without defining class \"$class\"", E_USER_ERROR);

      if (!in_array($trait, class_uses($class)))
        trigger_error("Model class ($class) does not use required trait \"$trait\"", E_USER_ERROR);

      echo "Integrating schema for model: $class\n";
      $class::integrate_schema();
    }

    die("-- Installation complete --\n");
  }

  /**
   * Runs after WordPress has finished loading but before any headers are sent.
   * Useful for intercepting $_GET or $_POST triggers.
   */
  public static function initialize() {
    foreach (new \DirectoryIterator(APP_DIR . 'controller') as $fi) {
      if ($fi->isDot())
        continue;

      if ($fi->getExtension() != 'php')
        continue;

      $class = $fi->getBasename('.php');

      static::$CONTROLLERS[] = $class;
    }
  }

  /**
   *
   */
  public function bootstrap() {
    preg_match('|^((?:/[^/]+)*)$|', $_SERVER['REQUEST_URI'], $matches);
      
    $uri = $matches[1] ?: 'index';
    $parts = explode('/', ltrim($uri, '/'));
    $controller = array_shift($parts);

    if (!self::load('controller/' . $controller . '.php'))
      self::error_404('Matching controller file not found.');
    else {
      $action = @array_shift($parts) ?: 'index';
      $this->control_request($controller, $action, $parts);
    }

    exit;
  }

  /**
   *
   */
  public static function enqueue_std_styles() {
    self::enqueue_style_tag('style.css');
  }

  /**
   *
   */
  public static function enqueue_style($i_content, $i_highPriority = false) {
    if ($i_highPriority)
      static::$STYLES = array_merge([$i_content], static::$STYLES);
    else
      static::$STYLES[] = $i_content;
  }

  /**
   *
   */
  public static function enqueue_style_tag($i_subpath, $i_highPriority = false) {
    if (strpos($i_subpath, '//') !== false)
      $url = $i_subpath;
    else {
      if (!is_file($path = TEMPLATE_DIR . $i_subpath))
        return;

      $url = TEMPLATE_URL . $i_subpath . '?ver=' . filemtime($path);
    }

    self::enqueue_style('<link rel="stylesheet" type="text/css" media="all" href="' . $url . '"/>', $i_highPriority);
  }

  /**
   *
   */
  public static function enqueue_std_scripts() {
    self::enqueue_script_tag('//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js');
    self::enqueue_script_tag('script.js');
  }

  /**
   *
   */
  public static function enqueue_script($i_content, $i_highPriority = false) {
    if ($i_highPriority)
      static::$SCRIPTS = array_merge([$i_content], static::$SCRIPTS);
    else
      static::$SCRIPTS[] = $i_content;
  }

  /**
   *
   */
  public static function enqueue_script_tag($i_subpath, $i_highPriority = false) {
    if (strpos($i_subpath, '//') !== false)
      $url = $i_subpath;
    else {
      if (!is_file($path = TEMPLATE_DIR . $i_subpath))
        return;

      $url = TEMPLATE_URL . 'js/' . basename($i_subpath) . '?ver=' . filemtime($path);
    }

    self::enqueue_script('<script type="text/javascript" src="' . $url . '"></script>', $i_highPriority);
  }

  /**
   *
   */
  public function render($i_file, array $i_varMap = null) {
    if ($i_varMap)
      extract($i_varMap);

    #@include(TEMPLATE_DIR . $i_file);
  }

  /**
   *
   */
  public static function has_controller($i_name) {
    return in_array(strtolower($i_name), static::$CONTROLLERS);
  }

  /**
   *
   */
  public static function get_instance() {
    return self::$s_obj;
  }

  /**
   *
   */
  public static function error_404($i_err = null) {
    header('HTTP/1.0 404 Not Found');

    $error = $i_err;
    require_once(TEMPLATE_DIR . '404.php');
    exit;
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   *
   */
  private function control_request($i_controllerName, $i_action, array $i_params) {
    $filename_prefix = str_replace('-', '', $i_controllerName);
    $class_prefix = str_replace('-', '', $i_controllerName);

    if (!self::has_controller($filename_prefix))
      self::error_404('Controller not loaded');

    $controller_class = ns($class_prefix . 'Controller');

    $controller = new $controller_class($i_action, $i_params);

    if (!$controller->is_valid_action($i_action)) {
      if ($i_action != 'index') {
        array_unshift($i_params, $i_action);
        $i_action = 'index';

        $controller = new $controller_class($i_action, $i_params);
      }
      else
        self::error_404('Invalid action requested');
    }

    $result = $controller->_call_action($i_action, $i_params);

    if (is_array($result)) {
      header('Content-Type: application/json');
      die(json_encode($result));
    }

    if (!self::load('view/' . $filename_prefix . '.php'))
      return $result;

    $view_class = ns($class_prefix . 'View');
    $view = new $view_class($controller);

    $output = $view->render();

    if ($output === false)
      self::error_404('View rendered blank');
    
    echo $output;
    exit;
  }
}

