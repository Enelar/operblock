<?php

class warehouse extends api
{
  protected function Reserve()
  {
    return
    [
      "design" => "warehouse/entry",
      "result" => "content",
    ];
  }

  protected function GenerateInvoice()
  {
    $res = db::Query("SELECT drug_id, sum(quantity_invoice) as amount FROM ServiceDrugOrder GROUP BY drug_id");
    return
    [
      "design" => "warehouse/invoice",
      "result" => "content",
      "data" => ["invoice" => $res],
    ];
  }

  protected function GenerateBill()
  {
    $res = db::Query("SELECT drug_id, sum(quantity_expense) as amount FROM ServiceDrugOrder GROUP BY drug_id");
    return
    [
      "design" => "warehouse/bill",
      "result" => "content",
      "data" => ["invoice" => $res],
    ];
  }

  protected function DrugName($id)
  {
    $res = db::Query("SELECT * FROM rbDrug WHERE code=?", [$id], true);
    return $res['name'];
  }

  public function AddInvoice($id)
  {
    $type = LoadModule('api', 'prescript')->GetOperationType($id);
    $res = db::Query("
        SELECT * 
          FROM rbServiceSpecification_Drug 
          WHERE specification_id=
          (
            SELECT id 
              FROM rbServiceSpecification 
              WHERE service_id=
              (
                SELECT nomenclativeService_id
                  FROM ActionType
                  WHERE id=?
              ) ORDER BY createDatetime DESC LIMIT 1
        )", [$type]);

    $trans = db::Begin();
    foreach ($res as $drug)
    {
      $did = $drug['drug_id'];
      $row = db::Query("SELECT * FROM ServiceDrugOrder WHERE drug_id=?", [$id], true);
      if (!count($row))
        db::Query("INSERT INTO ServiceDrugOrder(origin, drug_id, quantity_invoice) VALUES (?, ?, 0)", [$id, $did]);
      db::Query("UPDATE ServiceDrugOrder SET quantity_invoice=quantity_invoice+? WHERE origin=? AND drug_id=?", [$drug['quantity'], $id, $did]);
    }
    return $trans->Commit();
  }

  protected function PrepareBill($id)
  {
    $res = LoadModule('api', 'warehouse')->GenerateInvoice($id);
    return
    [
      "design" => "warehouse/prepare_bill",
      "result" => "content",
      "data" => ["bill" => $res],
    ];
  }

  protected function RegisterBill( )
  {
    global $_POST;
    var_dump($_POST);

    $trans = db::Begin();
    foreach ($_POST as $drug => $amount)
    db::Query("UPDATE ServiceDrugOrder SET quantity_expense=? WHERE drug_id=?",
      [$amount, $drug]);
    return $trans->Commit();
  }

  protected function Balance()
  {
    $res = db::Query("SELECT drug_id, sum(quantity_invoice) as invoice, sum(quantity_expense) as expense FROM ServiceDrugOrder GROUP BY drug_id");
    $today = date("Y-m-d H:i:s");  
    return
    [
      "design" => "warehouse/warehouse_balance",
      "data" => ["invoice" => $res, "date" => $today, "uid" => LoadModule("api", "user")->UID()],
    ];
  }
}