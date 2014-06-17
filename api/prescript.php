<?php

class prescript extends api
{
  public function __construct()
  {
    parent::__construct();
    $this->addons = ["cache" => ["no" => "global"]];
  }

  protected function FilterByStatus( $allowed_statuses )
  {
    LoadModule('api', 'user')->RequireAccess("operations.list");
    
    $filter = explode(",", $allowed_statuses);
    $arr = pgArrayFromPhp($filter, null);

    $res = db::Query("SELECT * FROM public.prescripts WHERE status = ANY($1) ORDER BY id DESC", [$arr]);

    return 
    [
      "data" => ["list" => $res],
      "design" => "prescript/list",
    ];
  }

  protected function GetList( $user = null )
  {
    LoadModule('api', 'user')->RequireAccess("operations.list");

    $events = LoadModule('api', 'event_action_manager')->SelectEventsByTypeNameAndPatient('operb_00', $user);
    //var_dump($events);
    $res = $this->TranslateEventsToList($events);
    
    $ret = [];
    foreach ($res as $row)
    {
      $operation_type = $row['prop']['operation_type']['value'];
      $operation_name = db::Query("SELECT * FROM ActionType WHERE id = :id", [":id" => $operation_type], true);
      $ret[] =
        [
          "id" => $row["id"],
          "type" => $operation_type,
          "create_date" => $row['created'],
          "description" => $operation_name['title'],
          "doctor" => $row['hivrach'],
          "patient" => $row['patient'],
          "planned_date" => $row["date"],
          "status" => $row['prop']['status']['value'],
        ];
    }
    
    return 
    [
      "data" => ["list" => $ret],
      "design" => "prescript/list",
    ];
  }
  
  private function TranslateEventsToList( $events )
  {
    $ret = [];
    $adaptor = LoadModule('api', 'event_action_manager');
    foreach ($events as $row)
    {
      $actions = $adaptor->SelectActionsFromEvent($row['id']);
      foreach ($actions as $action)
      {
        $type = db::Query("SELECT * FROM ActionType WHERE id=:id", [":id" => $action['actionType_id']], true);
        $properties = $adaptor->GetAllActionProperty($action['id']);
        $element =
          [
            "id" => $action['id'],
            "type" => $type['code'],
            "type_id" => $type['id'],
            "urgent" => $action['isUrgent'],
            "date" => $action['begDate'],
            "prop" => $properties,
            "created" => $action['createDatetime'],
            "created_by" => $action['createPerson_id'],
            "hivrach" => $action['person_id'],
            "patient" => $row['client_id'],
          ];

        $ret[] = $element;
      }
    }
    
    return $ret;
  }

  protected function Create( $patient, $type, $desc = NULL )
  {
    $user = LoadModule('api', 'user');
    $user->RequireAccess("operations.create");

    $manager = LoadModule('api', 'event_action_manager');
    $fictive_event_id = $manager->CreateEventByCode('operb_00', $patient);
    $action = $manager->CreateActionByCode("operb_cr_prescr", $fictive_event_id);

    phoxy_protected_assert($action, ["error" => "DB store failed"]);
    $manager->CreatePropertyByTypeShortName($action, 'status', 'UNCONFIRMED');
    $manager->CreatePropertyByTypeShortName($action, 'operation_type', $type);
    $manager->CreatePropertyByTypeShortName($action, 'operation_name', $desc);

    return $action;
  }
  
  private function ChangeStatus( $id, $permission, $to, $from = null )
  {
    LoadModule('api', 'user')->RequireAccess($permission);

    $adapter = LoadModule('api', 'event_action_manager');

    if ($from != null)
    {
      $trans = db::Begin();

      $property = $adapter->GetUniqueActionProperty($id, 'status');
      phoxy_protected_assert($property['value'] == $from, ["error" => "Вы не можете изменить статус этого направления"]);
    }

    $adapter->UpdateUniqueActionProperty($id, 'status', $to);

    if ($from != null)
      $trans->Commit();
    return $id;
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
    if ($role == 'hivrach')
    {
      db::Query("UPDATE Action SET person_id = :hivrach WHERE id=:id", [":id" => $prescript, ":hivrach" => $user]);
      return true;
    }

    $trans = db::Begin();

    $adapter = LoadModule('api', 'event_action_manager');
    LoadModule('api', 'event_action_manager')->DeletePropertyByName($prescript, $role);

    if (isset($user) && $user != '')
    {
      $uid = (int)$user;
      if ($uid)
        $id = $this->AddParticipantUser($prescript, $role, $uid);
      else
        $id = $this->AddParticipantName($prescript, $role, $user);
    }

    $trans->Commit();
    return $id;
  }

  private function AddParticipantUser( $prescript, $role, $uid )
  {
    return LoadModule('api', 'event_action_manager')->CreatePropertyByTypeShortName($prescript, $role, $uid);
  }

  private function AddParticipantName( $prescript, $role, $name )
  {
    return LoadModule('api', 'event_action_manager')->CreatePropertyByTypeShortName($prescript, $role, $name);
  }

  protected function Approve( $id )
  {
    $trans = db::Begin();

    if ($this->ApprovedByMyGroup($id))
      return $trans->Rollback();

    $this->SignApprove($id);

    return $trans->Finish($this->UpdateApproveStatus($id));
  }
  
  private function SignApprove( $id )
  {
    $user = LoadModule('api', 'user');
    $user->RequireAccess('operations.approve');
    $uid = $user->UID();

    db::Query("INSERT INTO public.approves(prescript, by) VALUES ($1, $2)", [$id, $uid]);
  }

  private function UpdateApproveStatus($id)
  {
    $trans = db::Begin();

    $row = db::Query('WITH prove AS
    (
      SELECT * FROM "public"."approves" WHERE prescript=$1
    )
    SELECT staff.group
      FROM users.staff
        JOIN prove
        ON staff.id=prove.by
      GROUP BY staff.group', [$id]);
    
    $status_id = count($row);

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
    $trans = db::Begin();

    $this->SignApprove($id);
    $this->ChangeStatus($id, "operations.confirm", "CONFIRMED", "THREE_APPROVED");
    
    return $trans->Commit();
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

  protected function MakeCritical( $id )
  {
    db::Query("UPDATE public.prescripts SET planned_date=now(), status='CRITICAL' WHERE id=$1", [$id], true);
  }
}
