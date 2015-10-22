<?php namespace RawMVC;

// Xref table between two models; aka: bridge table, join table, map table, link table

trait XrefTrait {
  /**
   *
   */
  public static function one_to_many($i_oneProp, $i_id, $i_manyProp, array $i_opts = []) {
    $id_fld = static::get_id_field();
    $one_fld = static::property_to_field($i_oneProp);
    $many_fld = static::property_to_field($i_manyProp);

    $opts = [
      'where' => "$one_fld = :$one_fld",
      'bind' => [":$one_fld" => $i_id],
      'order' => $id_fld,
    ];

    $opts = array_merge($opts, $i_opts);

    return static::get_col($many_fld, $opts);
  }

  /**
   *
   */
  public static function exists($i_propIdMap) {
    $id_fld = static::get_id_field();

    $opts = [
      'where' => [],
      'bind' => [],
    ];

    foreach ($i_propIdMap as $prop => $value) {
      $fld = static::property_to_field($prop);

      $opts['where'][] = "$fld = :$fld";
      $opts['bind'][":$fld"] = $value;
    }

    return static::get_var($id_fld, $opts);
  }

  /**
   *
   */
  public static function count_matches($i_propIdMap) {
    $opts = [
      'where' => [],
      'bind' => [],
    ];

    foreach ($i_propIdMap as $prop => $value) {
      $fld = static::property_to_field($prop);

      $opts['where'][] = "$fld = :$fld";
      $opts['bind'][":$fld"] = $value;
    }

    return static::get_var('COUNT(*)', $opts);
  }

  /**
   *
   */
  public static function cross_reference($i_propIdMap) {
    if (count($i_propIdMap) < 2)
      return false;

    $class = get_called_class();
    $obj = new $class;

    foreach ($i_propIdMap as $prop => $id)
      $obj->$prop = $id;

    return $obj->save() ? $obj->id : false;
  }

  /**
   *
   */
  public static function unlink($i_propIdMap) {
    $id_fld = static::get_id_field();

    $class = get_called_class();

    $opts = [
      'where' => [],
      'bind' => [],
    ];

    foreach ($i_propIdMap as $prop => $value) {
      $fld = static::property_to_field($prop);

      $opts['where'][] = "$fld = :$fld";
      $opts['bind'][":$fld"] = $value;

      $ids = self::get_col($id_fld, $opts);

      foreach ($ids as $id) {
        $obj = new $class($id);
        $obj->delete();
      }
    }
  }
}

