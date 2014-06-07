<?php

class operation extends api
{
  protected function GetList()
  {
    $res = db::Query("SELECT * FROM operations.types ORDER BY name ASC");
    $ret = [];
    foreach ($res as $row)
      $ret[$row['id']] = $row;
    return ['data' => ['list' => $ret]];
  }
}
