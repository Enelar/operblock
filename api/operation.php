<?php

class operation extends api
{
  protected function GetList()
  {
      $res = db::Query("SELECT id, title as name FROM ActionType WHERE class=2 AND (name like '%опера%' OR name like '%хирур%') AND nomenclativeService_id IS NOT NULL ORDER BY name ASC");
    $ret = [];
    foreach ($res as $row)
      $ret[$row['id']] = $row;
    return ['data' => ['list' => $ret]];
  }

  protected function AssignToGroup( $id, $group )
  {
    $eam = LoadModule('api', 'event_action_manager');
    
    $res = $eam->GetActionPropertiesByCode($id, 'day_group');
    if (!count($res))
      $eam->CreatePropertyByTypeShortName($id, 'day_group', $group);
    else
      $eam->UpdateUniqueActionProperty($id, 'day_group', $group);
    return true;
  }

  protected function ShowGroupList( )
  {
    $am = LoadModule('api', 'event_action_manager');
    $ret = [];

    for ($i = 1; $i < 10; $i++)
    {
      $res = 
        $am->FilterActionsByPropertyValue
        (
          "operb_cr_prescr", 
          "day_group", 
          [$i]
        );
      if (!count($res))
        break;
      $ret[$i] = $res;
    }
    return ["data" => $ret];
  }
}
