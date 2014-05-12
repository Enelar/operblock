<?php

class operation extends api
{
  protected function GetList()
  {
    $res = db::Query("SELECT * FROM operations.types ORDER BY name ASC");
    return ['data' => ['list' => $res]];
  }
}
