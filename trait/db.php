<?php namespace RawMVC;

trait DBTrait {
  public static $pdo;
  public static $error;

  /**
   * override and return map with keys:
   *    HOST, NAME, USER, PASS
   */
  protected static function get_auth_info() {
    trigger_error('DB Auth Info has not been defined by ' . get_called_class(), E_USER_ERROR);
  }

  /**
   *
   */
  public static function get_pdo() {
    if (!self::$pdo) {
      $auth = static::get_auth_info();

      $dsn = 'mysql:host=' . $auth['HOST'] . ';dbname=' . $auth['NAME'];

      $opts = [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
      ];

      try {
        self::$pdo = new \PDO($dsn, $auth['USER'], $auth['PASS'], $opts);
      }
      catch (\PDOException $e) {
        trigger_error($e->getMessage(), E_USER_ERROR);
      }
    }

    return self::$pdo;
  }

  /**
   *
   */
  private static function parse_query_opts(Array $i_opts) {
    if (!$i_opts)
      return '';

    $data = [];

    if ($table = @$i_opts['table'])
      $data['table'] = is_array($table) ? implode(',', $table) : $table;

    if ($where = @$i_opts['where']) {
      if (is_array($where) && !is_int(key($where))) {
        $bind = @$i_opts['bind'] ?: [];

        $cond = [];

        foreach ($where as $field => $value) {
          if (is_array($value)) {
            foreach ($value as $operator => $operand) {
              $cond[] = "$field $operator :$field";
              $bind[":$field"] = $operand;
            }
          }
          else {
            $cond[] = $field . ' = :' . $field;
            $bind[':' . $field] = $value;
          }
        }

        $where = implode(' AND ', $cond);
        $i_opts['bind'] = $bind;
      }

      $data['where'] = 'WHERE ' . (is_array($where) ? implode(' AND ', $where) : $where);
    }

    if ($limit = @$i_opts['limit'])
      $data['limit'] = 'LIMIT ' . $limit;

    if ($order = @$i_opts['order'])
      $data['order'] = 'ORDER BY ' . (is_array($order) ? implode(',', $order) : $order);

    if ($bind = @$i_opts['bind']) {
      foreach ($bind as $k => $v)
        if (!strlen($v))
          $bind[$k] = 0;

      $data['bind'] = $bind;
    }

    if (array_key_exists('result', $i_opts)) {
      switch ($result = @$i_opts['result']) {
        case \PDO::FETCH_ASSOC:
        case 'ASSOC':
        case 'assoc':
          $data['result'] = \PDO::FETCH_ASSOC;
          break;

        case \PDO::FETCH_OBJ:
        case 'OBJECT':
        case 'object':
          $data['result'] = \PDO::FETCH_OBJ;
          break;

        default:
          $data['result'] = \PDO::FETCH_NUM;
      }
    }

    return $data;
  }

  /**
   *
   */
  public static function quote($i_str) {
    return self::get_pdo()->quote($i_str);
  }

  /**
   *
   */
  public static function get_col($i_table, $i_column, array $i_opts = []) {
    $i_column = $i_column ?: '*';

    $opts = ['table' => $i_table];
    $opts = array_merge($opts, $i_opts);

    $segs = self::parse_query_opts($opts);

    $i_column = $i_column ?: '*';
    $cols = is_array($i_column) ? implode(',', $i_column) : $i_column;

    $query = "SELECT {$cols} FROM {$segs['table']} {$segs['where']} {$segs['order']} {$segs['limit']}";

    if (!$sth = self::query($query, @$segs['bind']))
      return [];

    $data = [];

    while (false !== ($value = $sth->fetchColumn()))
      $data[] = $value;
      
    return $data;
  }

  /**
   *
   */
  public static function get_var($i_table, $i_column, array $i_opts = []) {
    if (!has_str('COUNT', $i_column, false)) {
      if (strpos(@$i_opts['limit'], ',') !== false)
        $i_opts['limit'] = explode(',', $i_opts['limit'])[0] . ',1';
      else
        $i_opts['limit'] = 1;
    }

    return self::get_col($i_table, $i_column, $i_opts)[0];
  }

  /**
   *
   */
  public static function get_now() {
    static $cache = null;

    if (isset($cache))
      return $cache;

    $sth = self::query('SELECT UNIX_TIMESTAMP(NOW())');
    return $cache = $sth->fetchColumn(0);
  }

  /**
   *
   */
  public static function get_rows($i_table, $i_column = '*', Array $i_opts = null) {
    $i_column = $i_column ?: '*';

    $i_opts['table'] = $i_table;
    $i_opts['result'] = @$i_opts['result'] ?: \PDO::FETCH_NUM;

    $segs = self::parse_query_opts($i_opts);

    $i_column = $i_column ?: '*';
    $cols = is_array($i_column) ? implode(',', $i_column) : $i_column;

    $query = "SELECT {$cols} FROM `{$segs['table']}` {$segs['where']} {$segs['order']} {$segs['limit']}";

    if (!$sth = self::query($query, @$segs['bind']))
      return [];

    $data = [];

    while (false !== ($row = $sth->fetch($segs['result'])))
      $data[] = $row;
      
    return $data;
  }

  /**
   *
   */
  public static function get_row($i_table, $i_column = '*', Array $i_opts = null) {
    if (strpos(@$i_opts['limit'], ',') !== false)
      $i_opts['limit'] = explode(',', $i_opts['limit'])[0] . ',1';
    else
      $i_opts['limit'] = 1;

    if (!$row = self::get_rows($i_table, $i_column, $i_opts))
      return false;

    return $row[0];
  }

  /**
   *
   */
  public static function insert($i_table, Array $i_columnValues, array $i_opts = [], Array $i_bindManual = []) {
    $segs = self::parse_query_opts(['table' => $i_table]);

    $cols = array_keys($i_columnValues);
    $values = [];

    foreach ($cols as $col)
      $values[] = @$i_bindManual[$col] ?: ":$col";

    foreach ($i_bindManual as $col => $value) {
      if (!in_array($col, $cols)) {
        $cols[] = $col;
        $values[] = $value;
      }
    }

    $dupe_assigns = [];

    if (@$i_opts['dupe_update'])
      foreach ($cols as $i => $col)
        $dupe_assigns = "$col = {$values[$i]}";

    $cols = implode(',', $cols);
    $values = implode(',', $values);
    $dupe_assigns = implode(',', $dupe_assigns);

    $on_duplicate = $dupe_assigns ? "ON DUPLICATE KEY UPDATE $dupe_assigns" : '';

    $query = "INSERT IGNORE INTO {$segs['table']} ({$cols}) VALUES ({$values}) {$on_duplicate}";

    $bind = $segs['bind'] ?: [];

    foreach ($i_columnValues as $col => $value)
      if (empty($i_bindManual[$col]))
        $bind[':' . $col] = $value;

    if (!$sth = self::query($query, $bind))
      return false;

    return $sth->rowCount() ? self::get_pdo()->lastInsertId() : null;
  }

  /**
   *
   */
  public static function insert_update($i_table, Array $i_columnValues, Array $i_opts = [], Array $i_bindManual = []) {
    $i_opts['dupe_update'] = true;
    return self::insert($i_table, $i_columnValues, $i_opts, $i_bindManual);
  }

  /**
   *
   */
  public static function update($i_table, Array $i_columnValues, Array $i_opts = [], Array $i_bindManual = []) {
    $i_opts['table'] = $i_table;

    $segs = self::parse_query_opts($i_opts);

    $cols = array_keys($i_columnValues);

    $assigns = [];
    foreach ($cols as $col)
      $assigns[] = $col . ' = ' . (@$i_bindManual[$col] ?: ":$col");

    foreach ($i_bindManual as $col => $value)
      if (!in_array($col, $cols))
        $assigns[] = $col . ' = ' . $value;

    $assigns = implode(',', $assigns);

    $query = "UPDATE {$segs['table']} SET {$assigns} {$segs['where']} {$segs['limit']}";

    $bind = $segs['bind'] ?: [];

    foreach ($i_columnValues as $col => $value)
      if (empty($i_bindManual[$col]))
        $bind[':' . $col] = $value;

#echo "$query<br>"; print_r($bind); exit;

    $sth = self::query($query, $bind);
    return $sth ? $sth->rowCount() : false;
  }

  /**
   *
   */
  public static function delete($i_table, Array $i_columnValues, Array $i_opts = null) {
    $i_opts['table'] = $i_table;

    $segs = self::parse_query_opts($i_opts);

    $cols = array_keys($i_columnValues);

    $where = [];
    foreach ($cols as $col)
      $where[] = $col . ' = :' . $col;

    $where = implode(',', $where);

    $query = "DELETE FROM {$segs['table']} WHERE {$where} {$segs['limit']}";

    $bind = $segs['bind'] ?: [];

    foreach ($i_columnValues as $col => $value)
      $bind[':' . $col] = $value;

    $sth = self::query($query, $bind);
    return $sth ? $sth->rowCount() : false;
  }

  /**
   *
   */
  public static function describe($i_table, $i_resultType = null) {
    if (!$sth = self::query("DESCRIBE {$i_table}"))
      return false;

    $segs = self::parse_query_opts(['result' => $i_resultType]);

    $data = [];

    while (false !== ($row = $sth->fetch($segs['result'])))
      $data[] = $row;

    return $data;
  }

  /**
   *
   */
  public static function get_fields($i_table) {
    static $cache = [];

    if (@$cache[$i_table])
      return $cache[$i_table];

    if (!$meta_rows = self::describe($i_table, 'ASSOC'))
      return $cache[$i_table] = [];

    $fields = [];

    foreach ($meta_rows as $row)
      $fields[] = $row['Field'];

    return $cache[$i_table] = $fields;
  }

  /**
   *
   */
  public static function get_field_types($i_table, $i_raw = false) {
    static $cache = [];
    $ckey = "$i_table,$i_raw";

    if (@$cache[$ckey])
      return $cache[$ckey];

    if (!$meta_rows = self::describe($i_table, 'ASSOC'))
      return $cache[$ckey] = [];

    $fields = [];

    foreach ($meta_rows as $row) {
      $fld = $row['Field'];
      $type = $row['Type'];

      if ($i_raw) {
        $type = preg_replace('#^((?:big|medium|small|tiny)?int)\\(\\d+\\)#', '\\1', $type);

        $fields[$fld] = $type;

        if ($row['Extra'] == 'auto_increment')
          $fields[$fld] .= ' auto_increment';

        if ($row['Key'] == 'PRI')
          $fields[$fld] .= ' primary key';
        else if ($row['Null'] == 'NO')
          $fields[$fld] .= ' not null';

        if (strlen($row['Default'])) {
          if (substr($type, 0, 3) == 'set' ||
              substr($type, 0, 4) == 'enum' ||
              preg_match('#^(var)?char#', $type) ||
              preg_match('#^(tiny|medium|long)text#', $type))
            $fields[$fld] .= ' default \'' . $row['Default'] . '\'';
          else
            $fields[$fld] .= ' default ' . $row['Default'];
        }

        continue;
      }

      if (preg_match('#^(big|medium|small|tiny)?int#', $type))
        $fields[$fld] = 'int';
      else if (preg_match('#^(var)?char#', $type) || preg_match('#^(tiny|medium|long)text#', $type))
        $fields[$fld] = 'string';
      else if (preg_match('#^(var)?binary#', $type) || preg_match('#^(tiny|medium|long)blob#', $type))
        $fields[$fld] = 'binary';
      else if (preg_match('#^(float|double)#', $type))
        $fields[$fld] = 'float';
      else if (substr($type, 0, 3) == 'set')
        $fields[$fld] = 'set';
      else if (substr($type, 0, 4) == 'enum')
        $fields[$fld] = 'enum';
      else
        $fields[$fld] = $type;
    }

    return $cache[$ckey] = $fields;
  }

  /**
   *
   */
  public static function query($i_query, Array $i_bind = null) {
    if (!$sth = self::get_pdo()->prepare($i_query))
      return null;

    if (!$sth->execute($i_bind))
      return null;

    return $sth;
  }

  /**
   *
   */
  public static function table_exists($i_tableName) {
    if (!$sth = self::query('SHOW TABLES LIKE :table', [':table' => $i_tableName]))
      return null;

    return false !== $sth->fetchColumn();
  }

  /**
   *
   */
  public static function column_exists($i_tableName, $i_colName) {
    if (!$sth = self::query('SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = :table AND COLUMN_NAME = :column', [':table' => $i_tableName, ':column' => $i_colName]))
      return null;

    return false !== $sth->fetchColumn();
  }
}

