<?php

class staff extends api
{
  protected function FilterByJob( $filter )
  { // Presentation code
    if ($filter == 'zam')
      $query = "SELECT * FROM Person WHERE id=6790";
    else if ($filter == 'zav')
      $query = "SELECT * FROM Person WHERE id=3849";
    else if ($filter == 'anezav')
      $query = "SELECT * FROM Person WHERE id=3730";
    else if ($filter == 'smob')
      $query = "SELECT * FROM Person WHERE id=6585";
    else if ($filter == 'levrach')
      $query = "SELECT * FROM Person WHERE post_id=79";
    else if ($filter == 'hivrach')
      $query = "SELECT * FROM Person WHERE post_id=30";
    else if ($filter == 'mes')
      $query = "SELECT * FROM Person WHERE post_id=173";
    else if ($filter == 'anevrach')
      $query = "SELECT * FROM Person WHERE post_id=32";
    else if ($filter == 'anemes')
      $query = "SELECT * FROM Person WHERE post_id=174";
    else if ($filter == 'dracula')
      $query = "SELECT * FROM Person WHERE post_id=108";
/* Original pgsql final query
    $query = "
    WITH groups AS
    (
      SELECT id FROM users.user_groups WHERE name = ANY($1)
    )
    SELECT * FROM users.staff JOIN groups ON staff.group=groups.id";
    $filter = explode(",", $filter);
    $arr = pgArrayFromPhp($filter, null);

    $res = db::Query($query, [$arr]);
*/
    $res = db::Query($query, [$query]);

    $ret = [];
    foreach ($res as $row)
    {
      $row['name'] = "{$row['firstName']} {$row['patrName']} {$row['lastName']}";
      $ret[$row['id']] = $row;
    }

    return ["data" => ["staff" => $ret]];
  }
}
