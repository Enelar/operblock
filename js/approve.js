function AddPerpecients( id, array_of_params, cb_on_complete )
{
  if (!array_of_params.length)
    cb_on_complete();
  phoxy.ApiRequest
    (
    'prescript/AddParticipant',
    $.merge([id], array_of_params[0]), 
    function()
    {
      array_of_params.shift();
      AddPerpecients(id, array_of_params, cb_on_complete);
    })
}

function Approve( id, params, cb )
{
  AddPerpecients(id, params, function()
  {
    phoxy.ApiRequest('prescript/Approve', [id], function(data)
    {
      if (data.Approve == false)
        alert('Подтверждение не удалось. Возможно этот этап уже пройден');
      cb(data);
    });
  });
}

function ListModalStatusBoxButton()
{
  var ret = this.first().find('.status-box');
    ret
    .hover
    (
      function()
      {
        $(this).addClass('attention_glow');
      }
      ,
      function()
      {
        $(this).removeClass('attention_glow');
      }      
    )
    .click(function()
    {
      var that = $(this);
      var row 
        = that
            .parents("[data-mark='row']");
      var patient_name = row.find("[data-mark='patient']").html();
      var operation_id = row.find("[data-mark='id']").html();
      var snap = row.find("[data-mark='planned_date']").html();

      that.prop('disabled', true);
      $('#approve_modal')
        .modal('toggle');

      $('#approve_modal [data-mark="patient-name"]').html(patient_name);
      $('#approve_modal [data-mark="id"]').html(operation_id);
      $('#approve_modal [data-mark="planned_date"]').html(snap);

      phoxy.Defer(function()
      {
        that.prop('disabled', false);
      }, 100);
      FillEveryChildInputByName(operation_id, $('#approve_modal'));      
    })
    .each(function()
    {
      var that = $(this);
      var prescript = that.parents("[data-mark='row']").find("[data-mark='id']").html();
      phoxy.ApiRequest('prescript/ApprovedByMyGroup', [prescript], function(data)
      {
        if (data.ApprovedByMyGroup)
          that.trigger('complete');
      });
    });
}

function FillActionWithValue( operation_id, form, input_name, db_name )
{
  if (typeof db_name == 'undefined')
    db_name = input_name;
  var target = form.find("input[name='"+ input_name + "']");
  phoxy.ApiRequest("prescript/GetPrescriptParticipant", [operation_id, db_name], function(data)
  {
    var value = data.GetPrescriptParticipant;
    var parent = target.parent();
    if (!parent.is(".bfh-selectbox"))
      return target.val(value);
    parent.find("ul").find("a[data-option='"+ value +"']").click();
  });
}

function FillEveryChildInputByName( operation_id, parent )
{
  parent.find('input').each(function()
  {
    var name = $(this).attr('name');
    FillActionWithValue(operation_id, parent, name);
  });
}