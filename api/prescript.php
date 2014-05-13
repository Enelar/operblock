<?php

class prescript extends api
{
  protected function GetList()
  {
    LoadModule('api', 'user')->RequireAccess("operations.list");

    $res = db::Query("SELECT * FROM gray_prescripts ORDER BY id DESC");

    return ["data" => ["list" => $res]];
  }

  protected function Create( $patient, $type )
  {
    LoadModule('api', 'user')->RequireAccess("operations.create");
    $res = db::Query("INSERT INTO prescripts(doctor, patient, type) VALUES ($1, $2, $3) RETURNING id",
      [$this->UID(), $patient, $type]);
    phoxy_protected_assert($res, ["error" => "DB store failed"]);
    return $res['id'];
  }
  
  private function ChangeStatus( $id, $permission, $to, $from = null )
  {
    LoadModule('api', 'user')->RequireAccess("operations.delete");

    $query = "
      UPDATE prescripts
        SET status='DELETED'
        WHERE
          id=$1 ";
    $data = [$id];


    if (!is_null($from))
    {
      $query .= "AND status=$2 ";
      $data[] = $from;
    }

    $query .= "RETURNING id";


    $res = db::Query($query, $data, true);

    phoxy_protected_assert($res, ["error" => "No row found"]);
    return $res['id'];
  }
  
  protected function Delete( $id )
  {
    return $this->ChangeStatus($id, "operations.delete", "DELETED", "UNCONFIRMED");
  }
  
  protected function Cancel( $id, $reason )
  {
    return ["error" => "TODO: Add reason of cancelation"];
    // Maybe forbid cancel comleted?
    return $this->ChangeStatus($id, "operations.delete", "CANCELED");
  }
  
  protected function Approve( $id )
  {
    return ["error" => "TODO: Approve"];
  }
  
  protected function Confirm( $id )
  {
    return $this->ChangeStatus($id, "operations.confirm", "CONFIRMED", "THREE_APPROVED");
  }
  
  protected function Complete( $id )
  {
    return $this->ChangeStatus($id, "operations.complete", "COMPLETED", "CONFIRMED");
  }
  
  protected function Account( $id )
  {
    return ["error" => "TODO: Account consumables"];
    return $this->ChangeStatus($id, "operations.account", "ACCOUNTED", "CONFIRMED");
  }
  
}
