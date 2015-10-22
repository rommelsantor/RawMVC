<?php namespace RawMVC;

class Request {
  const CRAWLER_PATTERN = '#(googlebot|yahoo|slurp|msnbot|robot|feedburner|spider|crawl)#i';
  const MOBILE_PATTERN  = '#(mobile|tablet|ipad|iphone|palm|android|mini|symbian|blackberry|windows ce)#i';
  const PROXY_IPS       = '';
 
  /**
   *
   */
  public static function is_ajax() {
    return @$_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
  }

  /**
   *
   */
  public static function is_ssl() {
    return @$_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || @$_SERVER['SERVER_PORT'] == 443 || @$_SERVER['HTTPS'] == 'on';
  }

  /**
   *
   */
  public static function is_post() {
    return @$_SERVER['REQUEST_METHOD'] == 'POST';
  }

  /**
   *
   */
  public static function is_get() {
    return @$_SERVER['REQUEST_METHOD'] == 'GET';
  }

  /**
   *
   */
  public static function is_put() {
    return @$_SERVER['REQUEST_METHOD'] == 'PUT';
  }

  /**
   *
   */
  public static function is_crawler() {
    static $cached;
    if (isset($cached))
      return $cached;
    return $cached = preg_match(self::CRAWLER_PATTERN, @$_SERVER['HTTP_USER_AGENT']);
  }

  /**
   * Tests if current user is on a mobile device.
   */
  public static function is_mobile() {
    return preg_match(self::MOBILE_PATTERN, @$_SERVER['HTTP_USER_AGENT']);
  }

  /**
   *
   */
  public static function get_ip($i_asLong = false) {
    static $env_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
    static $ip = null;

    $skip_ips = r(self::PROXY_IPS);

    if ($ip)
      return $i_asLong ? ip2long($ip) : $ip;

    foreach ($env_keys as $key) {
      if (!$val = @$_SERVER[$key])
        continue;

      if (strcasecmp($val, 'unknown') == 0)
        continue;

      list($first) = explode(',', $val);
      $first = trim($first);

      if (preg_match('/^(\d{1,3}\.){3,3}\d{1,3}$/', $first)) {
        if (in_array($first, $skip_ips))
          continue;

        $ip = $first;
        return $i_asLong ? ip2long($ip) : $ip;
      }
    }

    return null;
  }
}

