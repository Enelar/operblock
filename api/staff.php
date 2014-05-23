<?php

class staff extends api
{
  protected function FilterByJob( $filter )
  {
    $query = "
    WITH groups AS
    (
      SELECT id FROM users.user_groups WHERE name = ANY($1)
    )
    SELECT * FROM users.staff JOIN groups ON staff.group=groups.id";
    $filter = explode(",", $filter);
    $arr = pgArrayFromPhp($filter, null);

    $res = db::Query($query, [$arr]);

    $ret = [];
    foreach ($res as $row)
      $ret[$row['id']] = $row;

    return ["data" => ["staff" => $ret]];
  }
}
