<?php

class patient extends api
{
  private $base_query = "SELECT *, CONCAT_WS(' ', lastName, firstName, patrName) as name FROM Client ";
  
  protected function GetFullList()
  {
    $res = db::Query("{$this->base_query} LIMIT 10");

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
    
    $res = db::Query("{$this->base_query} WHERE id=:id", [":id" => $patient], true);
    return [
      "design" => "people/patient",
      "data" => $res,
      "result" => "content"
    ];
  }

  protected function IdByIB( $ib )
  {
    $res = db::Query("SELECT * FROM Event WHERE externalId=:ib ORDER BY createDateTime DESC LIMIT 1", [":ib" => $ib], true);
    phoxy_protected_assert($res, ["error" => "Client not found"]);
    return ["data" => ["id" => $res['client_id'], "ib" => (int)$ib]];
  }
  
  protected function Name( $id )
  {
    return LoadModule('api', 'patient')->Info($id)['name'];
  }
}
