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

function ListModalStatusBoxButton()
{
  this
    .first()
    .find('.status-box')
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

      that.prop('disabled', true);
      $('#approve_smob_modal')
        .modal('toggle');

      $('#approve_smob_modal [data-mark="patient-name"]').html(patient_name);
      $('#approve_smob_modal [data-mark="id"]').html(operation_id);

      phoxy.Defer(function()
      {
        that.prop('disabled', false);
      }, 100);
    });
}