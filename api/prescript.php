<?php

class prescript extends api
{
  protected function GetList( $user = 'undefined' )
  {
    LoadModule('api', 'user')->RequireAccess("operations.list");

    $addon = $user == 'undefined' ? '' : 'WHERE patient=$1';
    $params = $user == 'undefined' ? [] : [$user];
    $query = "SELECT * FROM gray_prescripts $addon ORDER BY id DESC";

    $res = db::Query($query, $params);

    return 
    [
      "data" => ["list" => $res],
      "design" => "prescript/list",
    ];
  }

  protected function Create( $patient, $type, $desc = NULL )
  {
    $user = LoadModule('api', 'user');
    $user->RequireAccess("operations.create");
    $res = db::Query("INSERT INTO prescripts(doctor, patient, type, description) VALUES ($1, $2, $3, $4) RETURNING id",
      [$user->UID(), $patient, $type, $desc], true);
    phoxy_protected_assert($res, ["error" => "DB store failed"]);
    return $res['id'];
  }
  
  private function ChangeStatus( $id, $permission, $to, $from = null )
  {
    LoadModule('api', 'user')->RequireAccess("operations.delete");

    $query = "
      UPDATE prescripts
        SET status=$2
        WHERE
          id=$1 ";
    $data = [$id, $to];


    if (!is_null($from))
    {
      $query .= "AND status=$3 ";
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

  protected function AddParticipant( $prescript, $role, $user )
  {
    $trans = db::Begin();

    db::Query("DELETE FROM public.participants WHERE prescript=$1 AND role=$2",
      [$prescript, $role]);
    if (isset($user) && $user != '')
    {
      $uid = (int)$user;
      if ($uid)
        $this->AddParticipantUser($prescript, $role, $uid);
      else
        $this->AddParticipantName($prescript, $role, $user);
    }

    $trans->Commit();
  }

  private function AddParticipantUser( $prescript, $role, $uid )
  {
    db::Query("INSERT INTO public.participants
      (author, prescript, role, uid, name) VALUES
      ($1, $2, $3, $4, NULL)",
      [LoadModule('api', 'user')->UID(), $prescript, $role, $uid]);
  }

  private function AddParticipantName( $prescript, $role, $name )
  {
    db::Query("INSERT INTO public.participants
      (author, prescript, role, uid, name) VALUES
      ($1, $2, $3, NULL, $4)",
      [LoadModule('api', 'user')->UID(), $prescript, $role, $name]);
  }

  protected function Approve( $id )
  {
    $uid = LoadModule('api', 'user')->UID();
    $trans = db::Begin();

    if ($this->ApprovedByMyGroup($id))
      return $trans->Rollback();

    db::Query("INSERT INTO public.approves(prescript, by) VALUES ($1, $2)", [$id, $uid]);

    return $trans->Finish($this->UpdateApproveStatus($id));
  }

  private function UpdateApproveStatus($id)
  {
    $trans = db::Begin();

    $row = db::Query('WITH prove AS
    (
      SELECT * FROM "public"."approves" WHERE prescript=$1
    )
    SELECT count(staff.group)
      FROM users.staff
        JOIN prove
        ON staff.id=prove.by
      GROUP BY staff.group', [$id], true);
    
    $status_id = $row['count'];

    $statuses = ['UNCONFIRMED', 'ONE_APPROVED', 'TWO_APPROVED', 'THREE_APPROVED'];
    if ($status_id >= count($statuses))
      return $trans->Rollback();

    $status = $statuses[$status_id];
    
    db::Query("UPDATE public.prescripts SET status=$2 WHERE id=$1", [$id, $status]);
    
    return $trans->Commit();
  }

  protected function ApprovedByMyGroup($id)
  {
    $trans = db::Begin();
    $res = 
      db::Query(
        "SELECT count(\"group\")
          FROM users.staff 
            JOIN public.approves ON staff.id=approves.by 
          WHERE prescript=$1 AND \"group\"=$2", [$id, LoadModule('api', 'user')->Group()], true);
    $trans->Commit();

    return !!$res['count'];
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

  protected function ClericalLog( $id )
  {
    $res = db::Query("SELECT * FROM public.approves WHERE prescript=$1 ORDER BY snap DESC", [$id]);
    return ["data" => ["log" => $res]];
  }

  protected function UpdateSnap( $id, $snap )
  {
    db::Query("UPDATE public.prescripts SET planned_date=$2 WHERE id=$1", [$id, $snap], true);
  }
}
