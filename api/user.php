<?php

class user extends api
{
  protected function Login( $name )
  {
    $res = db::Query("SELECT id FROM users.staff WHERE name=$1", [$name], true);
    phoxy_protected_assert($res, ["error" => "Login or password wrong"]);

    global $_SESSION;
    session_start();
    $_SESSION['uid'] = $res['id'];
    return ["reset" => true];
  }
}
