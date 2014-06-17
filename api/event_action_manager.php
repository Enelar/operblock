<?php

class event_action_manager extends api
{
  public function FindEventTypeByCode( $code )
  {
    $event_type = db::Query("SELECT id FROM EventType WHERE code=:code", [":code" => $code], true);
    phoxy_protected_assert(isset($event_type['id']), ["error" => "Event type not registered"]);
    return $event_type['id'];
  }
  
  public function FindActionTypeByCode( $code )
  {
    $action_type = db::Query("SELECT id FROM ActionType WHERE code=:code", [":code" => $code], true);
    phoxy_protected_assert(isset($action_type['id']), ["error" => "Action type not registered"]);
    return $action_type['id'];
  }
  
  public function CreateEventByCode( $code, $patient )
  {
    $trans = db::Begin();
    $event_type = $this->FindEventTypeByCode($code);

    db::Query("
      INSERT INTO 
        Event(createDateTime, createPerson_id, eventType_id, client_id)
        VALUES
        (now(), :uid, :event, :customer)
        ",
      [
        ":uid" => LoadModule('api', 'user')->UID(), 
        ":event" => $event_type,
        ":customer" => $patient
      ], true);

    $event_id = db::RawConnection()->lastInsertId();
    phoxy_protected_assert($event_id, ["error" => "Event create failed"]);
    $trans->Commit();
    return $event_id;
  }

  public function CreateActionByCode( $code, $event )
  {
    $trans = db::Begin();
    $action_type = $this->FindActionTypeByCode($code);

    db::Query("
      INSERT INTO 
        Action(createDateTime, createPerson_id, actionType_id, event_id)
        VALUES
        (now(), :uid, :type, :event)",
      [
        ":uid" => LoadModule('api', 'user')->UID(),
        ":type" => $action_type,
        ":event" => $event
      ], true);

    $action_id = db::RawConnection()->lastInsertId();
    phoxy_protected_assert($action_id, ["error" => "Action create failed"]);
    $trans->Commit();
    return $action_id;
  }

  public function CreatePropertyByTypeShortName( $action, $short_name, $value )
  {
    $trans = db::Begin();
    $action_type = db::Query("SELECT ActionType_id as id FROM Action WHERE id=:id", [":id" => $action], true);
    phoxy_protected_assert(isset($action_type['id']), ["error" => "Could not find target action"]);
    $property_type = db::Query("SELECT * FROM ActionPropertyType WHERE actionType_id = :atype AND shortName = :name",
      [":atype" => $action_type['id'], ":name" => $short_name], true);
    phoxy_protected_assert(isset($property_type['id']), ["error" => "Could not find required property type('{$short_name}')"]);
    db::Query("
      INSERT INTO 
        ActionProperty(createDatetime, createPerson_id, action_id, type_id) 
          VALUES
        (now(), :uid, :action, :type)",
      [
        ":uid" => LoadModule('api', 'user')->UID(),
        ":action" => $action,
        ":type" => $property_type['id']
      ]);

    $property = db::RawConnection()->lastInsertId();
    phoxy_protected_assert($property, ["error" => "Action property handler create failed"]);

    db::Query("INSERT INTO ActionProperty_{$property_type['typeName']} (id, value) VALUES (:id, :value)",
      [":id" => $property, ":value" => $value]);

    $check = db::Query("SELECT count(*) as count FROM ActionProperty_{$property_type['typeName']} WHERE id = :id AND value = :value",
      [":id" => $property, ":value" => $value], true);

    phoxy_protected_assert($check['count'], ["error" => "Action property value store failed"]);
    $this->Commit();
    return $property;
  }
  
  public function SelectEventsByTypeNameAndPatient( $codename, $patient )
  {
    $event_type = $this->FindEventTypeByCode($codename);
    $res = 
      db::Query("SELECT * FROM Event WHERE eventType_id = :type AND client_id = :client",
        [
          ":type" => $event_type,
          ":client" => $patient
        ]);    
    return $res;
  }
  
  public function SelectActionsFromEvent( $event, $filter_type_name = null)
  {
    $q = "SELECT * FROM Action WHERE event_id = :event ";
    $p = [":event" => $event];
    
    if ($filter_type_name != null)
    {
      $type = $this->FindActionTypeByCode($filter_type_name);
      $q .= "AND actionType_id = :type";
      $p[":type"] = $type;
    }
    
    $ret = db::Query($q, $p);
    return $ret;
  }
  
  public function GetAllActionProperty( $action, $associative = true )
  {
    $ret = [];
    $res = db::Query("SELECT * FROM ActionProperty WHERE action_id = :action AND deleted=0",
      [":action" => $action]);    
    foreach ($res as $row)
    {
      $type = db::Query("SELECT * FROM ActionPropertyType WHERE id = :id", [":id" => $row['type_id']], true);
      $element =
        [
          "id" => $row['id'],
          "key" => $type['shortName'], 
          "title" => $type['name'],
          "type" => $type['typeName'],
          "create" => $row['createDatetime'],
          "create_by" => $row['createPerson_id'],
          "modified" => $row['modifyDatetime'],
          "modified_by" => $row['modifyPerson_id'],          
        ];

        $value = db::Query("SELECT * FROM ActionProperty_{$type['typeName']} WHERE id=:id", [":id" => $row['id']], true);
      $element['value'] = $value['value'];
      
      if ($associative)
        $ret[$element['key']] = $element;
      else
        $ret[] = $element;
    }

    return $ret;
  }
}
