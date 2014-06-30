<?php

class user extends api
{
  protected function Login( $name )
  {
    $res = db::Query("SELECT * FROM Person WHERE login=:name", [':name' => $name], true);
    phoxy_protected_assert(isset($res['id']), ["error" => "Login or password wrong"]);

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
    return $this->GetUID() != 0;
  }
  
  protected function UID()
  {
    phoxy_protected_assert(
      $this->IsLogined(), 
      [
        //"error" => "Login required. TODO: Redirect login page", 
        "reset" => '/'
      ]);

    return $this->GetUID();
  }

  protected function GetUID()
  {
    $this->addons['cache']['no'] = 'global';

    $this->StartSession();
    global $_SESSION;

    if (!isset($_SESSION['user_id']))
      $_SESSION['user_id'] = 0;
    return $_SESSION['user_id'];
  }

  protected function Group()
  {
    $res = db::Query("SELECT post_id as \"group\" FROM Person WHERE id=:id", [":id" => $this->UID()], true);
    phoxy_protected_assert($res, ["error" => "User account was deleted?"]);

    return $res['group'];
  }
  
  protected function GroupName()
  {
    $groups = LoadModule('api', 'user')->ExplainGroups()['byid'];
    return $groups[$this->Group()];
  }
  
  protected function Name( $uid )
  {
    $this->UID(); // Require login
    $res = db::Query(
      "SELECT 
          CONCAT_WS(' ', firstName, patrName, lastName) as name
        FROM Person WHERE id=:id", [":id" => $uid], true);
    if (!$res)
      $res['name'] = "Unknown";
    return
    [
      "design" => "people/name",
      "data" => ["Name" => $res['name']]
    ];
  }
  
  protected function ExplainGroups()
  {
    //$res = db::Query("SELECT * FROM rbPost ORDER BY id");
    $res = [];
    $res[] = ['name' => 'levrach', 'id' => 74];
    $res[] = ['name' => 'levrach', 'id' => 99];
    $res[] = ['name' => 'levrach', 'id' => 31];
    $res[] = ['name' => 'hivrach', 'id' => 30];
    $res[] = ['name' => 'mes', 'id' => 173];
    $res[] = ['name' => 'anevrach', 'id' => 32];
    $res[] = ['name' => 'anemes', 'id' => 174];
    $res[] = ['name' => 'dracula', 'id' => 46]; // should be 108
    $res[] = ['name' => 'zam', 'id' => 252];
    $res[] = ['name' => 'zav', 'id' => 46];
    $res[] = ['name' => 'anezav', 'id' => 240];
    $res[] = ['name' => 'anezav', 'id' => 32]; // cause of Rozengard's Group
    $res[] = ['name' => 'smob', 'id' => 138];

    $ret = [];

    foreach ($res as $row)
    {
      $ret['byid'][$row['id']] = $row['name'];
      $ret['toid'][$row['name']] = $row['id'];
    }

    return ['data' => $ret];
  }
  
  protected function HasAccessTo( $name )
  {
    return true; // ):
    $res = db::Query('SELECT * FROM users.group_rights WHERE "group"=$1 AND "right"::text=$2',
      [$this->Group(), $name], true);
    return !!$res;
  }
  
  public function RequireAccess( $name )
  {
    phoxy_protected_assert(
      $this->HasAccessTo($name), 
      ["error" => "Access to {$name} forbidden"]);
  }
  
  protected function GetRights()
  {
    $res = db::Query('SELECT "right" FROM users.group_rights WHERE "group"=$1 ORDER BY "right" ASC',
      [$this->Group()]);
    foreach ($res as $row)
      $ret[] = $row['right'];
    return ["data" => ["rights" => $ret]];
  }
  
  protected function Hivrach()
  {
    $res = LoadModule('api', 'prescript')->FilterByStatus('CONFIRMED,CRITICAL');
    $uid = $this->UID();
    $ret = [];
    foreach ($res['list'] as $row)
      if ($row['doctor'] == $uid)
        $ret[] = $row;
    
    return
    [
      "design" => "levrach/complete",
      "result" => "content",
      "data" => ["list" => $ret]
    ];
  }
}
