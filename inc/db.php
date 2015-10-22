<?php namespace RawMVC;

class DB {
  use DBTrait;

  /**
   *
   */
  protected static function get_auth_info() {
    return [
      'HOST'  => @constant('DB_HOST'),
      'NAME'  => @constant('DB_NAME'),
      'USER'  => @constant('DB_USER'),
      'PASS'  => @constant('DB_PASSWORD'),
    ];
  }
}

