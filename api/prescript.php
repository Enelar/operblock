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

    $am = LoadModule('api', 'event_action_manager');
    $res = 
      $am->FilterActionsByPropertyValue
      (
        "operb_cr_prescr", 
        "status", 
        count($filter) > 1
          ? $filter
          : $allowed_statuses
      );

    foreach ($res as &$row)
      $row['client_id'] = $am->GetClientByEvent($row['event_id']);
    $ret = $this->TranslateActionsToList($res);

    return 
    [
      "data" => ["list" => $this->TranslateListToOurFormat($ret)],
      "design" => "prescript/list",
    ];
  }

  protected function GetList( $user = null )
  {
    LoadModule('api', 'user')->RequireAccess("operations.list");

    $events = LoadModule('api', 'event_action_manager')->SelectEventsByTypeNameAndPatient('operb_00', $user);

    $res = $this->TranslateEventsToList($events);
    $ret = $this->TranslateListToOurFormat($res);

    return 
    [
      "data" => ["list" => $ret],
      "design" => "prescript/list",
    ];
  }

  private function TranslateListToOurFormat( $res )
  {
    $ret = [];
    foreach ($res as $row)
    {
      $operation_type = $row['prop']['operation_type']['value'];
      $operation_name = db::Query("SELECT * FROM ActionType WHERE id = :id", [":id" => $operation_type], true);
      if (count($operation_name))
        $operation_name = $operation_name['title'];
      else
        $operation_name = "NULL";
      $ret[] =
        [
          "id" => $row["id"],
          "type" => $operation_type,
          "create_date" => $row['created'],
          "description" => $operation_name,
          "doctor" => $row['hivrach'],
          "patient" => $row['patient'],
          "planned_date" => $row["date"],
          "status" => $row['prop']['status']['value'],
        ];
    }
    return $ret;
  }
  
  private function TranslateEventsToList( $events )
  {
    $res = [];
    $adaptor = LoadModule('api', 'event_action_manager');
    foreach ($events as $row)
    {
      $actions = $adaptor->SelectActionsFromEvent($row['id']);
      foreach ($actions as $action)
      {
        $action['client_id'] = $row['client_id'];
        $res[] = $action;
      }
    }
    
    return $this->TranslateActionsToList($res);
  }

  private function TranslateActionsToList( $actions )
  {
    $ret = [];
    $adaptor = LoadModule('api', 'event_action_manager');
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
          "patient" => $action['client_id'],
        ];

      $ret[] = $element;
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

    LoadModule('api', 'event_action_manager')->CreatePropertyByTypeShortName($id, 'sign', $uid);
    db::Query("INSERT INTO public.approves(prescript, by) VALUES ($1, $2)", [$id, $uid]);
  }

  private function UpdateApproveStatus($id)
  {
    $trans = db::Begin();

    $signs = LoadModule('api', 'event_action_manager')->GetActionPropertiesByCode($id, 'sign');
    
    $status_id = count($signs);

    $statuses = ['UNCONFIRMED', 'ONE_APPROVED', 'TWO_APPROVED', 'THREE_APPROVED'];
    if ($status_id >= count($statuses))
      return $trans->Rollback();

    $status = $statuses[$status_id];
    
    if ($status_id > 0)
      $prev = $statuses[$status_id - 1];
    else
      $prev = null;
    $this->ChangeStatus($id, 'operations.approve', $status, $prev);
    
    return $trans->Commit();
  }

  protected function ApprovedByMyGroup($id)
  {
    $group = LoadModule('api', 'user')->Group();
    $trans = db::Begin();

    $res = LoadModule('api', 'event_action_manager')->GetActionPropertiesByCode($id, 'sign');
    
    $count = 0;
    foreach ($res as $row)
    {
      $data = db::Query("SELECT post_id as 'group' FROM Person WHERE id=?", [$row['create_by']], true);
      if ($data['group'] == $group)
        $count++;
    }

    $trans->Commit();

    return !!$count;
  }
  
  protected function Confirm( $id )
  {
    $trans = db::Begin();

    $this->SignApprove($id);
    $this->ChangeStatus($id, "operations.confirm", "CONFIRMED", "THREE_APPROVED");
    $this->_NastyDeadlineHack_CalculateBalance($id);
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
    $res = LoadModule('api', 'event_action_manager')->GetActionPropertiesByCode($id, 'sign');
    $ret = [];
    foreach ($res as $row)
      $ret[] = 
        [
          "by" => $row['value'], 
          "snap" => $row['create']
        ];
    return ["data" => ["log" => $ret]];
  }
  
  protected function UpdateSnap( $id, $snap )
  {
    db::Query("UPDATE public.prescripts SET planned_date=$2 WHERE id=$1", [$id, $snap], true);
  }

  protected function MakeCritical( $id )
  {
    $this->ChangeStatus($id, "operations.confirm", "CRITICAL");
    db::Query("UPDATE Action SET begDate=now(), isUrgent=1 WHERE id=?", [$id], true);  
  }

  protected function GetPrescriptParticipant( $id, $role )
  {
    if ($role != 'hivrach')
    {
      $res = LoadModule('api', 'event_action_manager')->GetActionPropertiesByCode($id, $role);      
      return end($res)['value'];      
    }
    $res = db::Query("SELECT * FROM Action WHERE id=?", [$id], true);
    return $res['person_id'];
  }

  protected function _NastyDeadlineHack_CalculateBalance( $id )
  {
    return LoadModule('api', 'warehouse')->AddInvoice($id);
  }

  public function GetOperationType( $id )
  {
    $res = LoadModule('api', 'event_action_manager')->GetUniqueActionProperty($id, 'operation_type');
    return $res['value'];
  }

  protected function SaveState( $id, $state, $val )
  {
    // todo check that this is state
    $manager = LoadModule('api', 'event_action_manager');
    $trans = db::Begin();
    $property = $manager->GetActionPropertiesByCode($id, $state);

    if (!$property)
      $manager->CreatePropertyByTypeShortName($id, $state, $val);
    else
      $manager->UpdateUniqueActionProperty($id, $state, $val);
    return $trans->Commit();
  }

  protected function GetState( $id, $state )
  {
    $manager = LoadModule('api', 'event_action_manager');
    if (!db::Query("SELECT person_id FROM Action WHERE prescript=:id", [":id" => $id], true))
      $ret = "";
    else if ($state == 'hivrach')
      $ret = db::Query("SELECT person_id FROM Action WHERE prescript=:id", [":id" => $id], true)['person_id'];
    else if ($state == 'levrach')
      $ret = db::Query("SELECT created_by FROM Action WHERE prescript=:id", [":id" => $id], true)['created_by'];
    else
    {
      $property = $manager->GetActionPropertiesByCode($id, $state);
      if (!$property)
        $ret = "";
      else
        $ret = $property[0]['value'];
    }

    return
    [
      "data" => ["value" => $ret],
      "design" => "utils/show_simple_param",
    ];
  }
}