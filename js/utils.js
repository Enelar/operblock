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