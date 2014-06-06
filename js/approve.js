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