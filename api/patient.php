<?php

class patient extends api
{
  protected function GetFullList()
  {
    $res = db::Query("SELECT * FROM users.patients");
    $ret = [];
    foreach ($res as $row)
    {
      $id = $row['id'];
      unset($row['id']);
      $ret[$id] = $row;
    }
    return ['data' => ['patients' => $ret], 'cache' => ['no' => 'global']];
  }

  protected function Select()
  {
    return array_merge(
      ['design' => 'people/patients'],
      $this->GetFullList()
    );
  }

  protected function Info( $patient )
  {
    LoadModule('api', 'user')->RequireAccess("patients.brief_info");
    
    $res = db::Query("SELECT * FROM users.patients WHERE id=$1", [$patient], true);
    return [
      "design" => "people/patient",
      "data" => $res,
      "result" => "content"
    ];
  }
  
  protected function Name( $id )
  {
    return LoadModule('api', 'patient')->Info($id)['name'];
  }
}
