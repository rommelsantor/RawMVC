<?php namespace RawMVC;

trait ModelTrait {
  private static function db_table_name() { return ''; }
  private static function db_field_prefix() { return ''; }

  /**
   * map of property names to values
   */
  private function get_defaults() { return []; }

  private function after_create() { }
  private function after_delete() { }

  /**
   *
   */
  private static function get_id_field() {
    return self::property_to_field('id');
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   *
   */
  public function __construct($i_id = null, $i_properties = null) {
    if ($i_id)
      $this->load($i_id, $i_properties);
  }

  /**
   * translate object var to db column
   */
  public static function property_to_field($i_prop) {
    if (is_array($i_prop)) {
      foreach ($i_prop as $i => $prop)
        $i_prop[$i] = self::property_to_fields($prop);

      return $i_prop;
    }

    return self::db_field_prefix() . $i_prop;
  }

  /**
   *
   */
  public static function field_to_property($i_field) {
    return self::db_field_prefix() ?
      preg_replace('#^' . self::db_field_prefix() . '#', '', $i_field) : $i_field;
  }

  /**
   *
   */
  public static function get_table_schema() {
    return false;
  }

  /**
   *
   */
  public static function integrate_schema() {
    $class = get_called_class();

    if (!$sql = self::get_table_schema())
      trigger_error("$class::get_table_schema() defined in model file without any content", E_USER_ERROR);

    $sql = preg_replace('#-- .*$#m', '', $sql);

    if (!preg_match('#^([^(]+)\\s*\\(\\s*(.+)\\s*\\)\\s*([^)]+)$#Us', $sql, $m))
      trigger_error("$class::get_table_schema() defined in model file returned unsupported schema syntax", E_USER_ERROR);

    list($create, $coldefs, $charcoll) = array_slice($m, 1);

    if (strpos($create, ';') !== false)
      trigger_error("$class::get_table_schema() defined in model file defines multiple SQL statements", E_USER_ERROR);

    $coldefs = trim($coldefs);

    if (!preg_match_all('#^\\s*(\`?[A-Za-z_]+\`?)(\\s+.+|\\(.+\\))$#m', $coldefs, $m))
      trigger_error("$class::get_table_schema() defined in model file returned unsupported column definition syntax (one-per-line required)", E_USER_ERROR);

    foreach ($m[1] as $i => $key) {
      if (preg_match('#^(?:UNIQUE|INDEX|KEY|FOREIGN|PRIMARY|CONSTRAINT)#i', $key)) {
        $m[1][$i] = $i;
        $m[2][$i] = $key . $m[2][$i];
      }
    }

    $new_coldef_map = array_combine($m[1], array_map(function($s) { return rtrim(trim($s), ','); }, $m[2]));

    try {
      preg_match('#([^\\s]+)$#', $create, $m);

      $table = preg_replace('#^([^A-Za-z])(.+)\\1$#', '\\2', $m[1]);
      $exists = DB::table_exists($table);

      // the table simply does not exist - create the entire thing for the first time
      if (!$exists) {
        $q = "$create (\n  $coldefs\n) $charcoll";
        DB::query($q);
      }
      // it was previously added; make any alterations that may have happened
      else {
        $old_coldef_map = self::get_field_types(true);

        $adds = [];
        $drops = [];
        $mods = [];

        foreach ($new_coldef_map as $col => $def) {
          if (preg_match('#^(?:UNIQUE|INDEX|KEY|FOREIGN|PRIMARY|CONSTRAINT)#i', $def)) {
            trigger_error("$class::get_table_schema() defined in model specifies \"{$def}\" - this must be changed by hand\n", E_USER_NOTICE);
            continue;
          }

          if (empty($old_def = $old_coldef_map[$col]))
            $adds[] = $col;
          else {
            $def = preg_replace('#^((?:big|medium|small|tiny)?int)\\(\\d+\\)#', '\\1', $def);
            $def = strtolower($def);

            if ($old_def != $def || array_search($col, array_keys($new_coldef_map)) != array_search($col, array_keys($old_coldef_map)))
              $mods[] = $col;
          }
        }

        foreach ($old_coldef_map as $col => $def) {
          if (empty($new_coldef_map[$col]))
            $drops[] = $col;
        }

        foreach ($drops as $col) {
          unset($old_coldef_map[$col]);
          DB::query("ALTER TABLE $table DROP $col");
        }

        $preceding = null;

        foreach ($new_coldef_map as $col => $def) {
          if (in_array($col, $adds)) {
            $disposition = !$preceding ? 'FIRST' : "AFTER $preceding";
            DB::query("ALTER TABLE $table ADD $col $def $disposition");
          }
          else if (in_array($col, $mods)) {
            $disposition = !$preceding ? 'FIRST' : "AFTER $preceding";
            DB::query("ALTER TABLE $table MODIFY $col $def $disposition");
          }

          $preceding = $col;
        }
      }
    }
    catch (\Exception $e) {
      trigger_error("$class::get_table_schema() defined in model file has SQL throwing an exception:\n  " . trim($e->getMessage()) . '<br/>', E_USER_ERROR);
    }
  }

  /**
   *
   */
  public function is_loaded() {
    return !empty($this->id);
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   *
   */
  public static function get_fields() {
    static $fields;

    if (isset($fields))
      return $fields;

    return $fields = DB::get_fields(self::db_table_name());
  }

  /**
   *
   */
  public static function has_field($i_field) {
    return in_array($i_field, self::get_fields());
  }

  /**
   *
   */
  public static function has_property($i_propName) {
    $fld = self::property_to_field($i_propName);
    return in_array($fld, self::get_fields());
  }

  /**
   *
   */
  public static function get_field_types($i_raw = false) {
    return DB::get_field_types(self::db_table_name(), $i_raw);
  }
  
  /**
   *
   */
  public static function get_var($i_column, array $i_opts = []) {
    return DB::get_var(self::db_table_name(), $i_column, $i_opts);
  }

  /**
   *
   */
  public static function get_rows($i_column = '*', Array $i_opts = null) {
    return DB::get_rows(self::db_table_name(), $i_column, $i_opts);
  }

  /**
   *
   */
  public static function get_row($i_column = '*', Array $i_opts = null) {
    return DB::get_row(self::db_table_name(), $i_column, $i_opts);
  }

  /**
   *
   */
  public static function get_col($i_column, Array $i_opts = []) {
    return DB::get_col(self::db_table_name(), $i_column, $i_opts);
  }

  /**
   *
   */
  public static function insert(Array $i_columnValues, Array $i_opts = [], Array $i_bindManual = []) {
    return DB::insert(self::db_table_name(), $i_columnValues, $i_opts, $i_bindManual);
  }

  /**
   *
   */
  public static function insert_update(Array $i_columnValues, Array $i_opts = [], Array $i_bindManual = []) {
    return DB::insert_update(self::db_table_name(), $i_columnValues, $i_opts, $i_bindManual);
  }

  /**
   *
   */
  public static function update(Array $i_columnValues, Array $i_opts = [], Array $i_bindManual = []) {
    return DB::update(self::db_table_name(), $i_columnValues, $i_opts, $i_bindManual);
  }

  /**
   *
   */
  public static function query_delete(Array $i_columnValues, Array $i_opts = null) {
    return DB::delete(self::db_table_name(), $i_columnValues, $i_opts);
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////

  /**
   *
   */
  public function has_opt($i_key) {
    return Option::find(get_called_class(), $this->id, $i_key);
  }

  /**
   *
   */
  public function set_opt($i_key, $i_value) {
    return Option::set(get_called_class(), $this->id, $i_key, $i_value);
  }

  /**
   *
   */
  public function get_opt($i_key) {
    return Option::get(get_called_class(), $this->id, $i_key);
  }

  /**
   *
   */
  public function delete_opt($i_key) {
    return Option::delete(get_called_class(), $this->id, $i_key);
  }

  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////
  //////////////////////////////////////////////////////////////////////////////
  
  /**
   *
   */
  public function save(array $i_allowProps = null) {
    if (!$field_types = self::get_field_types())
      return false;

    $func = $this->is_loaded() ? 'update' : 'insert';

    if ($func == 'insert') {
      if (array_key_exists(self::property_to_field('adduser'), $field_types))
        $this->adduser = User::cuid();

      if (array_key_exists(self::property_to_field('adddate'), $field_types))
        $this->adddate = 'NOW';

      foreach ($this->get_defaults() as $var => $value)
        if (!isset($this->$var) && isset($value))
          $this->$var = $value;
    }
    else {
      if (array_key_exists(self::property_to_field('chguser'), $field_types))
        $this->chguser = User::cuid();

      if (array_key_exists(self::property_to_field('chgdate'), $field_types))
        $this->chgdate = 'NOW';

      foreach ($this->get_defaults() as $var => $value)
        if (!isset($this->$var) && isset($value))
          $this->$var = $value;
    }

    $assigns = [];
    $manual_binds = [];

    foreach ($field_types as $field => $type) {
      $prop = self::field_to_property($field);

      if ($i_allowProps)
        if (!in_array($prop, $i_allowProps))
          continue;

      if ($func == 'insert')
        if (!isset($this->$prop))
          continue;

      $value = @$this->$prop;

      switch ($type) {
        case 'datetime':
        case 'date':
        {
          if (in_array($value, ['NOW', 'NOW()', 'now']))
            $manual_binds[$field] = 'NOW()';
          else if (ctype_digit($value))
            $manual_binds[$field] = "FROM_UNIXTIME('$value')";
          break;
        }

        case 'set': {
          if (!$value) {
            $value = '';
            $manual_binds[$field] = 'NULL';
          }
          else if (is_array($value)) {
            $r = [];

            foreach ($value as $setval)
              $r[] = DB::quote($setval);

            $value = implode(',', $r);
            $manual_binds[$field] = $value;
          }

          break;
        }
      }

      $assigns[$field] = $value;
    }

    $opts = [
      'limit' => 1
    ];

    if ($func == 'update') {
      $id_col = self::get_id_field();

      $opts['where'] = "$id_col = :$id_col";
      $opts['bind'] = [":$id_col" => $this->id];
    }

    $result = self::$func($assigns, $opts, $manual_binds);

    if ($func == 'insert' && $result) {
      $this->id = $result;

      $this->after_create();
    }

    return $result;
  }

  /**
   *
   */
  public function load($i_id, $i_properties = null) {
    if (!$field_types = self::get_field_types())
      return false;

    if ($i_properties && !is_array($i_properties))
      $i_properties = [$i_properties];

    $fields = [
      self::get_id_field()// always load the id
    ];

    foreach ($field_types as $field => $type) {
      if ($i_properties && !in_array(self::field_to_property($field), $i_properties))
        continue;

      switch ($type) {
        case 'datetime':
        case 'date':
        {
          $fields[] = "UNIX_TIMESTAMP($field) AS $field";
          break;
        }

        default: {
          $fields[] = $field;
        }
      }
    }

    $opts = [
      'where' => self::db_field_prefix() . "id = :id",
      'bind' => [':id' => $i_id],
      'result' => 'assoc',
    ];
    
    if (!$row = self::get_row($fields, $opts))
      return false;

    foreach ($row as $field => $value) {
      switch ($field_types[$field]) {
        case 'set': {
          $r = [];

          foreach (explode(',', $value) as $setval)
            if (strlen($setval))
              $r[] = $setval;

          $value = $r;
          break;
        }
      }

      $prop = self::field_to_property($field);
      $this->$prop = $value;
    }

    return true;
  }

  /**
   *
   */
  public function delete() {
    if (!$this->is_loaded())
      return false;

    $id_col = self::get_id_field();

    $colvals = ["$id_col" => $this->id];
    $opts = ['limit' => 1];

    $result = self::query_delete($colvals, $opts);

    $this->after_delete();

    return $result;
  }

  /**
   *
   */
  public static function delete_id($i_id) {
    $class = get_called_class();
    (new $class($i_id))->delete();
  }

  /**
   *
   */
  public static function settings($i_key) {
    return null;
  }
}

