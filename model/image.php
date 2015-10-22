<?php namespace RawMVC;

//
// actual usable Model class for example and for use if desired
//

class Image {
  use ModelTrait;

  const JPG_QUALITY = 90; // 0 = worst quality, 100 = best quality
  const PNG_QUALITY = 0;  // 0 = no compression, 9 = most compression

  const BASENAME_MAXLEN = 64;

  public $m_handle;

  private static function db_table_name() { return 'images'; }
  private static function db_field_prefix() { return 'img_'; }

  /**
   *
   */
  public static function get_table_schema() {
    return <<<EOF
CREATE TABLE IF NOT EXISTS `images` (
  img_id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  --
  img_adduser       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  img_adddate       DATETIME,
  --
  img_mimetype      VARCHAR(16),
  img_width         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  img_height        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  img_dirname       VARCHAR(128),-- ex. /www/domain.com/htdocs/public-images/
  img_subfolder     VARCHAR(64),-- ex. /2015/10/21/folder
  img_basename      VARCHAR(64),-- ex. puppy-doggy.jpg
  img_urlfolder     VARCHAR(64),
  --
  INDEX(img_adduser)
) DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE=MyISAM
EOF;
  }

  /**
   *
   */
  public static function settings($i_key) {
    switch ($i_key) {
    }

    return null;
  }

  /**
   *
   * /
  public function update_path($i_newpath) {
    $old_path = $this->dirname . $this->basename;

    $this->dirname = dirname($i_newpath) . '/';
    # TODO: update $this->subfolder
    $this->basename = self::filter_basename(basename($i_newpath));
    $this->save(['dirname', 'basename']);

    @rename($old_path, $i_newpath);
  }

  /**
   *
   */
  public static function id_to_url($i_id) {
    return !$i_id ? false :
      (new Image($i_id, ['urlfolder', 'basename']))->get_url();
  }

  /**
   * This returns NULL if the object is not saved or the image file is not
   * stored somewhere under the public document root directory.
   */
  public function get_url() {
    if (!$this->is_loaded())
      return null;

    return url($this->urlfolder . '/' . $this->basename . '?v=' . $this->id);
  }

  /**
   *
   */
  public function load_saved_file() {
    if (!$filepath = $this->dirname . $this->basename)
      return null;

    switch (explode('/', $this->mimetype)[1]) {
      case 'jpg':
      case 'jpeg':
        $this->m_handle = imagecreatefromjpeg($filepath);
        break;

      case 'png':
        $this->m_handle = imagecreatefrompng($filepath);
        imagealphablending($this->m_handle, false);
        imagesavealpha($this->m_handle, true);
        break;

      case 'gif':
        $this->m_handle = imagecreatefromgif($filepath); break;

      default:
        return null;
    }

    return !empty($this->m_handle);
  }

  /**
   *
   */
  public function create_from_url($i_url) {
    if (!function_exists('curl_init'))
      trigger_error('cURL module does not appear to be enabled', E_USER_ERROR);

    $tmp_file = tempnam(sys_get_temp_dir(), 'smi');
    $h = fopen($tmp_file, 'wb');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $i_url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; pl; rv:1.9) Gecko/2008052906 Firefox/3.0');
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_ENCODING, 'deflate');
    curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FILE, $h);

    curl_exec($ch);
    curl_close($ch);

    fclose($h);

    $ret = $this->create_from_disk($tmp_file);

    @unlink($tmp_file);

    return $ret;
  }

  /**
   *
   */
  public function create_from_disk($i_filepath) {
    $sizeinfo = @getimagesize($i_filepath);

    switch (@$sizeinfo[2]) {
      case IMAGETYPE_GIF:
        $handle = imagecreatefromgif($i_filepath);
        break;

      case IMAGETYPE_JPEG:
        $handle = imagecreatefromjpeg($i_filepath);
        break;

      case IMAGETYPE_PNG:
        $handle = imagecreatefrompng($i_filepath);
        imagealphablending($handle, false);
        imagesavealpha($handle, true);
        break;

      default:
        return null;
    }

    if (empty($handle))
      return false;

    $this->m_handle = $handle;
    $this->mimetype = $sizeinfo['mime'];
    $this->width    = $sizeinfo[0];
    $this->height   = $sizeinfo[1];

    return true;
  }

  /**
   *
   */
  public function create_from_upload($i_file) {
    if (!$this->create_from_disk(@$i_file['tmp_name']))
      return false;

    $this->mimetype = $i_file['type'];
    $this->basename = self::filter_basename($i_file['name']);

    return true;
  }

  /**
   *
   */
  public function resize_width($i_width, $i_cropheight = 0, $i_grow = FALSE) {
    if (!$this->m_handle)
      return;

    if (!$i_grow) {
      if ($i_width > $this->width) {
        $this->crop_height($i_cropheight);
        return;
      }
    }

    $resize_height = intval(($i_width / $this->width) * $this->height);

    if ($this->width >= $i_width && $i_cropheight && $resize_height < $i_cropheight) {
      $this->resize_height($i_cropheight, $i_width);
      return;
    }

    $orig = array(
      'x' => 0,
      'y' => 0,
      'cx' => $this->width,
      'cy' => $this->height
    );

    $dest = array(
      'x' => 0,
      'y' => 0,
      'cx' => $i_width,
      'cy' => $resize_height
    );

    $this->resize($orig, $dest);

    if ($i_cropheight)
      $this->crop_height($i_cropheight);
  }

  /**
   *
   */
  public function resize_height($i_height, $i_cropwidth = 0, $i_grow = FALSE) {
    if (!$this->m_handle)
      return;

    if (!$i_grow) {
      if ($i_height > $this->height) {
        $this->crop_width($i_cropwidth);
        return;
      }
    }

    $resize_width = intval(($i_height / $this->height) * $this->width);

    if ($this->height >= $i_height && $i_cropwidth && $resize_width < $i_cropwidth) {
      $this->resize_width($i_cropwidth, $i_height);
      return;
    }

    $orig = array(
      'x' => 0,
      'y' => 0,
      'cx' => $this->width,
      'cy' => $this->height
    );

    $dest = array(
      'x' => 0,
      'y' => 0,
      'cx' => $resize_width,
      'cy' => $i_height
    );

    $this->resize($orig, $dest);

    if ($i_cropwidth)
      $this->crop_width($i_cropwidth);
  }

  /**
   *
   */
  public function crop_width($i_width) {
    if (!$this->m_handle || !$i_width || $this->width < $i_width)
      return;

    $orig = array(
      'x' => intval($this->width / 2) - intval($i_width / 2),
      'y' => 0,
      'cx' => $i_width,
      'cy' => $this->height
    );

    $dest = array(
      'x' => 0,
      'y' => 0,
      'cx' => $i_width,
      'cy' => $this->height
    );

    $this->resize($orig, $dest);
  }

  /**
   *
   */
  public function crop_height($i_height) {
    if (!$this->m_handle || !$i_height || $this->height < $i_height)
      return;

    $orig = array(
      'x' => 0,
      'y' => intval($this->height / 2) - intval($i_height / 2),
      'cx' => $this->width,
      'cy' => $i_height
    );

    $dest = array(
      'x' => 0,
      'y' => 0,
      'cx' => $this->width,
      'cy' => $i_height
    );

    $this->resize($orig, $dest);
  }

  /**
   *
   */
  public static function filter_basename($i_basename) {
    if (strlen($i_basename) > self::BASENAME_MAXLEN) {
      if (preg_match('#-(?:[a-z]|\d+)\.[a-z]{3,4}$#U', $i_basename, $m))
        $ext = $m[0];
      else
        $ext = '.' . pathinfo($i_basename, PATHINFO_EXTENSION);

      $i_basename = substr($i_basename, 0, self::BASENAME_MAXLEN - strlen($ext)) . $ext;
    }

    return $i_basename;
  }

  /**
   * $i_filename if NULL will cause the image to just be dumped to the client
   * $i_type may be one of 'jpg', 'gif', 'png'; if it is not supplied and $i_filename
   *  includes one of those extensions, it will be used
   */
  public function output($i_filename = null, $i_type = null, $i_quality = null) {
    if (!$this->m_handle)
      return null;

    $abs_path = null;
    $subfolder = null;

    if ($i_filename) {
      // absolute path
      if (in_array($i_filename[0], ['/', '\\']))
        $abs_path = $i_filename;
      // relative path/filename
      else {
        if (($subfolder = dirname($i_filename)) == '.')
          $subfolder = null;
        else
          $subfolder = "/$subfolder";

        $abs_path = IMAGE_DIR . $i_filename;
      }

      $dirname = dirname($abs_path);

      if (!is_dir($dirname))
        @mkdir($dirname, 0777, true);

      if (file_exists($abs_path))
        @unlink($abs_path);
    }

    if (empty($i_type)) {
      if (preg_match('/\.(jpe?g|png|gif)$/i', $i_filename, $matches))
        $i_type = $matches[1];
      else
        $i_type = explode('/', $this->mimetype)[1];
    }

    $i_type = strtolower($i_type);
    $this->mimetype = 'image/' . $i_type;

    switch ($i_type) {
      case 'jpg':
      case 'jpeg':
      {
        if ($i_quality === null || ($i_quality < 0 || $i_quality > 100))
          $i_quality = self::JPG_QUALITY;

        if (!imagejpeg($this->m_handle, $abs_path, $i_quality))
          return FALSE;

        break;
      }

      case 'png': {
        if ($i_quality === NULL || ($i_quality < 0 || $i_quality > 9))
          $i_quality = self::PNG_QUALITY;

        imagealphablending($this->m_handle, false);
        imagesavealpha($this->m_handle, true);

        if (!imagepng($this->m_handle, $abs_path, $i_quality))
          return FALSE;

        break;
      }

      case 'gif': {
        if (!imagegif($this->m_handle, $abs_path))
          return FALSE;

        break;
      }
    }

    if ($abs_path) {
      $this->dirname    = $dirname . '/';
      $this->subfolder  = $subfolder;
      $this->basename   = self::filter_basename(basename($abs_path));

      @chmod($abs_path, 0664);
    }

    return true;
  }

  /*****************************************************************************
   *****************************************************************************
   * PROTECTED
   *****************************************************************************
   ****************************************************************************/

  /**
   *
   */
  protected function resize($i_orig, $i_dest) {
    if (!$this->m_handle)
      return;

    $thumb_handle = imagecreatetruecolor($i_dest['cx'], $i_dest['cy']);

    imagealphablending($thumb_handle, false);
    imagesavealpha($thumb_handle, true);

    imagecopyresampled($thumb_handle, $this->m_handle,
      $i_dest['x'], $i_dest['y'], $i_orig['x'], $i_orig['y'],
      $i_dest['cx'], $i_dest['cy'], $i_orig['cx'], $i_orig['cy']);

    imagedestroy($this->m_handle);
    $this->m_handle = $thumb_handle;

    $this->width = $i_dest['cx'];
    $this->height = $i_dest['cy'];
  }
}

