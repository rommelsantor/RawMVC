<?php namespace RawMVC;

trait ViewTrait {
  private $controller;
  private $headtags;
  private $foottags;

  /**
   *
   */
  private function get_id() {
    return strtolower(substr(un_ns(get_called_class()), 0, -4));
  }

  /**
   *
   */
  private function get_action_name() {
    return $this->controller->action_alias ?: $this->controller->action;
  }

  /**
   *
   */
  private function find_target($i_actionName = null) {
    $id = $this->get_id();
    $action_name = $i_actionName ?: $this->get_action_name();

    $prefix = TEMPLATE_DIR . $id;

    $action_path = $prefix . '/' . $action_name . '.php';

    if (is_file($action_path))
      return $action_path;

    if ($i_actionName) {
      if (is_file($action_path = TEMPLATE_DIR . $action_name . '.php'))
        return $action_path;

      return false;
    }

    if (is_file($action_path = $prefix . '/index.php'))
      return $action_path;

    if (is_file($action_path = $prefix . '.php'))
      return $action_path;

    return false;
  }

  /**
   *
   */
  private function render_header() {
    if ($path = $this->find_target('header')) {
      echo $this->fetch($path, [
        'headtags' => 
          "<!--HEADTAGS-->\n" .
          trim($this->headtags) . "\n" .
          "<!--/HEADTAGS-->\n" .
          "<!--STYLES-->\n" .
          trim(implode("\n", Kernel::$STYLES)) . "\n" .
          "<!--/STYLES-->\n"
      ]);
    }
  }

  /**
   *
   */
  private function render_footer() {
    if ($path = $this->find_target('footer')) {
      echo $this->fetch($path, [
        'foottags' =>
          "<!--FOOTTAGS-->\n" .
          trim($this->foottags) . "\n" .
          "<!--/FOOTTAGS-->\n" .
          "<!--SCRIPTS-->\n" .
          trim(implode("\n", Kernel::$SCRIPTS)) . "\n" .
          "<!--/SCRIPTS-->\n"
      ]);
    }
  }

  /**
   *
   */
  public function __construct($i_controllerObj = null) {
    if (!$this->controller = $i_controllerObj)
      return;

    $this->headtags = <<<EOF
<title>{$this->controller->get_title()}</title>
EOF;

    $id = $this->get_id();

    foreach (glob(TEMPLATE_DIR . $id . '/*.css') as $file) {
      if (($basename = basename($file)) != 'style.css')
        Kernel::enqueue_style(TEMPLATE_URL . $id . '/' . $basename . '?ver=' . filemtime($file));
    }

    if (is_file($file = TEMPLATE_DIR . $id . '/style.css'))
      Kernel::enqueue_style(TEMPLATE_URL . $id . '/' . $basename . '?ver=' . filemtime($file));

    if (is_file($file = TEMPLATE_DIR . 'js/script.js'))
      Kernel::enqueue_script(TEMPLATE_URL . 'js/' . basename($file) . '?ver=' . filemtime($file));

    $TEMPLATE_URL = @constant('TEMPLATE_URL');
    $model_id = intval($this->controller->get_model_id());
    $loading = tpl_url('media/loading.gif');
    $login_url = login_url('http' . (@$_SERVER['HTTPS'] ? 's' : '') . '://' . @$_SERVER['HTTP_HOST'] . @$_SERVER["REQUEST_URI"]);

    ob_start();

    echo <<<EOF
<script>
var rawmvc = {
  model_id : {$model_id},
  loading : '{$loading}',
  login_url : '{$login_url}',
  cu : {// current user object
  }
};
</script>
EOF;

    ?>
<script>
(function($){
  rawmvc.url = function(path) {
    if (/\/\//.test(path))
      return path;

    return APP_URL + path;
  };

  rawmvc.tpl_url = function(path) {
    return rawmvc.TEMPLATE_URL + '/' + path.replace(/^\//, '');
  };

  rawmvc.startLoading = function($replacee, $disablees) {
    $replacee = $($replacee);

    var $img = $('<img src="<?=$loading?>" alt="Loading..."/>').css({
      verticalAlign : 'middle',
      position : $replacee.css('position'),
      top : $replacee.css('top'),
      left : $replacee.css('left'),
      right : $replacee.css('right')
    });
    
    $img.insertAfter($replacee);

    var els = [];

    if ($disablees) {
      if (!Array.isArray($disablees))
        $disablees = [$disablees];

      for (var i in $disablees) {
        var $el = $($disablees[i]);

        if (!$el.is(':disabled'))
          els.push($el.prop({ disabled : true }));
      }
    }

    $replacee.data({ loading : $img, disablees : els }).css({ opacity : 0 });
  };

  rawmvc.endLoading = function($replacee) {
    $replacee = $($replacee);

    var $img = $replacee.data('loading');
    var $disablees = $replacee.data('disablees');

    if ($img)
      $img.remove();

    if ($disablees) {
      $($disablees).each(function(){
        $(this).prop({ disabled : false });
      });
    }

    $replacee.data({ loading : null, disablees : null }).css({ opacity : 1 });
  };
})(jQuery);
</script>
    <?php

    foreach (glob(TEMPLATE_DIR . $id . '/*.js') as $file) {
      if (($basename = basename($file)) != 'script.js')
        echo '<script type="text/javascript" src="' . TEMPLATE_URL . $id . '/' . $basename . '?ver=' . filemtime($file) . '"></script>';
    }

    if (is_file($file = TEMPLATE_DIR . $id . '/script.js'))
      echo '<script type="text/javascript" src="' . TEMPLATE_URL . $id . '/' . basename($file) . '?ver=' . filemtime($file) . '"></script>';

    $this->foottags = ob_get_clean();
  }

  /**
   *
   */
  public function render() {
    if (!$path = $this->find_target())
      return false;

    $this->controller->before_render();

    ob_start();

    $this->render_header();

    if ($vars = (array)$this->controller->vars) {
      if (array_key_exists('error', $vars) && is_array($vars['error']))
        $vars['error'] = '<ul><li>' . implode('</li><li>', $vars['error']) . '</li></ul>';

      extract($vars);
    }

    @include($path);

    $this->render_footer();

    return ob_get_clean();
  }

  /**
   *
   */
  public function fetch($i_path, array $i_varMap = null) {
    if ($i_varMap)
      extract($i_varMap);

    if ($i_path[0] != '/')
      $i_path = constant('TEMPLATE_DIR') . $i_path;

    ob_start();
    @include($i_path);
    return ob_get_clean();
  }

  /**
   *
   */
  public function a_tag($i_url, $i_body, $i_attr = []) {
    $s = '<a href="' . url($i_url) . '" ';

    foreach ($i_attr as $k => $v)
      $s .= $k . '="' . ent($v) . '" ';

    return trim($s) . '>' . $i_body . '</a>';
  }

  /**
   *
   */
  public function get_url($i_path) {
    return tpl_url($i_path);
  }
}

