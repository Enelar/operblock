<?php

class operation extends api
{
  protected function GetList()
  {
    $res = db::Query("SELECT id, title as name FROM ActionType WHERE class=2 ORDER BY name ASC");
    $ret = [];
    foreach ($res as $row)
      $ret[$row['id']] = $row;
    return ['data' => ['list' => $ret]];
  }
}
