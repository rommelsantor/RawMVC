<?php namespace RawMVC;

/**
 *
 */
function die_404() {
  status_header(404);
  include(get_404_template());
}

/**
 * requires a value to have only one in a specific set of possible values or default
 */
function only($i_value, $i_validValues, $i_defaultValue = '') {
  if (is_scalar($i_validValues))
    $i_validValues = [$i_validValues];

  return in_array($i_value, $i_validValues) ? $i_value : $i_defaultValue;
}

/**
 * requires a value to be between a min and max; use null to bypass
 */
function within($i_value, $i_min, $i_max) {
  if (isset($i_min))
    $i_value = max($i_value, $i_min);

  if (isset($i_max))
    $i_value = min($i_value, $i_max);
  
  return $i_value;
}

/**
 *
 */
function paginate(\stdClass $i_gd, $i_count, &$o_perpage, &$o_page, &$o_totalPages, &$o_limit = null) {
  $o_perpage = within(@$i_gd->pp, 3, 25);
  $o_page = max(@$i_gd->page, 1);
  $o_totalPages = ceil($i_count / $o_perpage) ?: 1;
  $o_limit = (($o_page - 1) * $o_perpage) . ',' . $o_perpage;
}

/**
 *
 */
function array_remove(&$io_array, $i_cond, $i_strict = false) {
  $removed_values = [];

  foreach ($io_array as $k => $value) {
    if (is_callable($i_cond)) {
      if ($i_cond($value)) {
        $removed_values[] = $value;
        unset($io_array[$k]);
      }
    }
    else if ($strict && $value === $i_cond) {
      $removed_values[] = $value;
      unset($io_array[$k]);
    }
    else if (!$strict && $value == $i_cond) {
      $removed_values[] = $value;
      unset($io_array[$k]);
    }
  }

  return $removed_values;
}

/**
 *
 */
function get_mime_type($i_file) {
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mimetype = finfo_file($finfo, $i_file);
  finfo_close($finfo);

  return $mimetype;
}

/**
 *
 */
function url($i_path) {
  if (strpos($i_path, '//') !== false)
    return $i_path;

  return '/' . REWRITE_ROOT . '/' . $i_path;
}

/**
 *
 */
function qstr_url(array $i_varMap, $i_path = null) {
  $i_path = $i_path ?: parse_url(@$_SERVER['REQUEST_URI'], PHP_URL_PATH);

  parse_str($_SERVER['QUERY_STRING'], $qstr);

  if (!$qstr = array_merge($qstr, $i_varMap))
    return $i_path;

  return $i_path . '?' . http_build_query($qstr);
}

/**
 * keys are the field names and values are the default sort dir ("asc" or "desc")
 */
function qstr_sort_url($i_order, $i_defaultDir) {
  $dir = $i_defaultDir;

  if (@$_GET['order'] == $i_order) {
    $cur_dir = @$_GET['dir'];

    if (in_array($cur_dir, ['asc', 'desc']))
      $dir = $cur_dir == 'asc' ? 'desc' : 'asc';
  }

  return qstr_url(['order' => $i_order, 'dir' => $dir]);
}

/**
 *
 */
function tpl_url($i_path) {
  return constant('TEMPLATE_URL') . ltrim($i_path, '/');
}

/**
 *
 */
function login_url($i_returnUri) {
  return '/?login-url-goes-here&return_url=' . urlencode($i_returnUri);
}

/**
 *
 */
function tpl_fetch($i_path, array $i_varMap = null) {
  if ($i_varMap)
    extract($i_varMap);

  ob_start();
  @include(constant('TEMPLATE_DIR') . $i_path);
  return ob_get_clean();
}

/**
 *
 */
function has_str($i_needle, $i_haystack, $i_insensitive = true) {
  $func = $i_insensitive ? 'stripos' : 'strpos';

  if (is_scalar($i_haystack))
    return $func($i_haystack, $i_needle) !== false;

  if (is_array($i_haystack))
    foreach ($i_haystack as $el)
      if (has_str($i_needle, $el))
        return true;

  return false;
}

/**
 *
 */
function ns($i_name) {
  if (strpos($i_name, __NAMESPACE__ . '\\') === false)
    $i_name = __NAMESPACE__ . '\\' . $i_name;

  return $i_name;
}


/**
 *
 */
function un_ns($i_name) {
  $prefix = __NAMESPACE__ . '\\';
  $len = strlen($prefix);

  if (substr($i_name, 0, $len) == $prefix)
    $i_name = substr($i_name, $len);

  return $i_name;
}

/**
 *
 */
function ent($i_str) {
  return \htmlentities($i_str);
}

/**
 *
 */
function object_merge($i_lhs, $i_rhs) {
  return (object)array_merge((array)$i_lhs, (array)$i_rhs);
}

/**
 *
 */
function iff($i_test, $i_value) {
  return $i_test ? $i_value : '';
}

/**
 *
 */
function checked($i_test) {
  return iff($i_test, 'checked');
}

/**
 *
 */
function selected($i_test) {
  return iff($i_test, 'selected');
}
