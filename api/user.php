<?php

class user extends api
{
  protected function Login( $name )
  {
    $res = db::Query("SELECT id FROM users.staff WHERE name=$1", [$name], true);
    phoxy_protected_assert($res, ["error" => "Login or password wrong"]);

    $this->MakeLogin($res['id']);
    return ["reset" => true];
  }

  private function MakeLogin( $uid )
  {
    $this->StartSession();
    global $_SESSION;
    $_SESSION['uid'] = $uid;
    return $this->UID();
  }
  
  protected function Logout()
  {
    $this->MakeLogin(0);
    return ["reset" => true];
  }
  
  private function StartSession()
  {
    if (session_status() == PHP_SESSION_ACTIVE)
      return;
    session_start();
  }

  protected function IsLogined()
  {
    $this->wrap = 'is_logined';
    return $this->UID() != 0;
  }
  
  protected function UID()
  {
    $this->addons['cache']['no'] = 'global';

    $this->StartSession();
    global $_SESSION;
    if (!isset($_SESSION['uid']))
      $_SESSION['uid'] = 0;
    return $_SESSION['uid'];
  }

  protected function Group()
  {
    $res = db::Query("SELECT \"group\" FROM users.staff WHERE id=$1", [$this->UID()], true);
    phoxy_protected_assert($res, ["error" => "User account was deleted?"]);

    return $res['group'];
  }
  
  protected function ExplainGroups()
  {
    $res = db::Query("SELECT * FROM users.user_groups ORDER BY id");
    $ret = [];

    foreach ($res as $row)
    {
      $ret['byid'][$row['id']] = $row['name'];
      $ret['toid'][$row['name']] = $row['id'];
    }

    return ['data' => $ret];
  }
}
