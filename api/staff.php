<?php

class staff extends api
{
  protected function FilterByJob( $ofilter )
  { // Presentation code
    $dictonary = LoadModule('api', 'user')->ExplainGroups();

    $filter = explode(",", $ofilter);
    $groups = [];
    foreach ($filter as $group)
      $groups[] = $dictonary['toid'][$group];

    $in  = str_repeat('?,', count($filter) - 1) . '?';
    if  ($ofilter == 'hivrach')
      $res = db::Query("SELECT * FROM Person");
    else
      $res = db::Query("SELECT * FROM Person WHERE post_id IN ({$in})", $groups);

    $ret = [];
    foreach ($res as $row)
    {
      $row['name'] = "{$row['firstName']} {$row['patrName']} {$row['lastName']}";
      $ret[$row['id']] = $row;
    }

    return ["data" => ["staff" => $ret]];
  }
}
