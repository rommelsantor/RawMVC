<?php namespace RawMVC;

trait ControllerTrait {
  public $id = '';
  public $action = '';
  public $params = [];

  public $vars = [];

  public $action_alias = null;

  private $gd = [];
  private $pd = [];
  private $fd = [];

  /**
   *
   */
  private function get_id() {
    return strtolower(substr(un_ns(get_called_class()), 0, -10));
  }

  /**
   * must return FALSE to deny access; any other result will allow access
   */
  private function validate_user_access() {
  }

  /**
   *
   */
  private function parse_get() {
    $this->gd = (object)array_map(ns('stripslashes_deep'), $_GET);
  }

  /**
   *
   */
  private function parse_post() {
    if (@$_SERVER['REQUEST_METHOD'] != 'POST') {
      $this->pd = (object)[];
      $this->fd = (object)[];
      return;
    }

    $_POST = array_map(ns('stripslashes_deep'), $_POST);
    $this->pd = (object)@$_POST;

    $this->fd = (object)(@$_FILES ?: []);
  }

  /**
   *
   */
  private function redirect($i_subUrl, $i_statusCode = 302) {
    wp_redirect('/' . REWRITE_ROOT . '/' . ltrim($i_subUrl, '/'), $i_statusCode);
    exit;
  }

  /**
   *
   */
  public function __construct($i_action, array $i_params) {
    $this->parse_get();
    $this->parse_post();
    
    $this->id = $this->get_id();
    $this->action = $i_action;
    $this->params = $i_params;

    $this->vars = (object)$this->vars;

    if ($this->validate_user_access() === false) {
      header('HTTP/1.1 403 Forbidden');
      \auth_redirect();
    }
  }

  /**
   *
   */
  public function is_valid_action($i_name) {
    if (@$i_name[0] == '_')
      return false;

    if ($i_name == __FUNCTION__)
      return false;

    if (!method_exists(get_called_class(), $i_name))
      return false;

    $rm = new \ReflectionMethod(get_called_class(), $i_name); 
    return $rm->isPublic();
  }

  /**
   *
   */
  public function index() {
  }

  /**
   *
   */
  public function _call_action($i_action, $i_params) {
    $this->vars = (object)$this->pd;

    return call_user_func_array(array($this, $i_action), $i_params);
  }

  /**
   *
   */
  public function before_render() {
  }

  /**
   *
   */
  public function get_model_id() {
    return null;
  }

  /**
   *
   */
  public function get_upload_error($i_varName) {
    if (!isset($this->fd->$i_varName))
      return 'No upload was submitted';

    switch ($this->fd->{$i_varName}['error']) {
      case UPLOAD_ERR_OK:
        break;

      case UPLOAD_ERR_INI_SIZE:
        return 'Size exceeds server\'s configured max';

      case UPLOAD_ERR_FORM_SIZE:
        return 'Size exceeds submission form\'s max';

      case UPLOAD_ERR_PARTIAL:
        return 'File upload was interrupted';

      case UPLOAD_ERR_NO_FILE:
        return 'No file was uploaded';

      case UPLOAD_ERR_NO_TMP_DIR:
        return 'Server\'s upload folder does not exist';

      case UPLOAD_ERR_CANT_WRITE:
        return 'Server\'s disk is not writable';

      case UPLOAD_ERR_EXTENSION:
        return 'Server extension halted the upload';
    }

    return false;
  }

  /**
   *
   */
  public function get_title() {
    return @constant('APP_TITLE');
  }
}

