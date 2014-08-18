phoxy.Defer(function()
{
  var old_f = $.fn.ajaxForm;

  if (typeof(old_f) == 'undefined')
    return phoxy.Defer(arguments.callee, 50);

  $.fn.ajaxForm = function(options)
  {
    options = options || {};
    var origin_cb = options.success;

    options.success = function(response)
    {
      var obj = $.parseJSON(response);

      phoxy.ApiAnswer(obj);

      if (typeof(origin_cb) == 'function')
        origin_cb.apply(this, arguments);
    };

    return old_f.call(this, options);
  };
});

function PopUp( url )
{
  var d = new Date();
  var name = d.getTime()
  $("<a href=\"" + url + "\" onclick=\"javascript:void window.open('" + url + "','"+ name +"','width=700,height=500,toolbar=1,menubar=1,location=1,status=1,scrollbars=1,resizable=1,left=0,top=0');return false;\">Pop-up Window</a>").click();
}