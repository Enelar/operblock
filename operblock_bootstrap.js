var operblock = {};
operblock.WaitFor = function( condition, cb, check_interval )
{
  if (typeof check_interval == 'undefined')
    check_interval = 50;
  var me = arguments.callee;
  if (!condition())
    return setTimeout(
      function()
      {
        me.call(this, condition, cb, check_interval + 50);
      }, check_interval);
  cb();
};

operblock.AutoloadOperblock = function()
{
  operblock.WaitFor( function() { return document.body != null },
    function()
    {
      console.log("Operblock required");
      var d = document;
      var js = d.createElement("script");
      js.type = "text/javascript";
      js.src = "/web/operblock/libs/require.js";
      d.body.appendChild(js);
      operblock.ManualLoadPhoxyWOJqueryConflict();
    });
};

operblock.ManualLoadPhoxyWOJqueryConflict = function()
{
  operblock.WaitFor( function() { return typeof require != 'undefined' },
    function ()
    {
      console.log("Operblock require patched");
      define("//ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js", [], function() {return {}});
      var d = document;
      require(["/web/operblock/phoxy/phoxy.js"]);
      operblock.PatchPhoxy();
    });
};

operblock.PatchPhoxy = function()
{ // Ignore hash change (fix conflict with doctor_room)
  if (typeof(phoxy) == 'undefined')
    return setTimeout(arguments.callee, 0);
  console.log("Operblock loaded");
  var old_compile = phoxy.Compile;
  phoxy.Compile = function()
  {
    console.log("Operblock phoxy recompiled");
    old_compile();
    phoxy.ChangeHash = function() { return false; };

    var old_load = phoxy.Load;
    phoxy.Load = function()
    {
      console.log("Operblock phoxy loaded");
      var old_api = phoxy.ApiRequest;
      phoxy.ApiRequest = function()
      { // ignore forced first time api call
        phoxy.ApiRequest = old_api;
      }
      old_load();

      var old_download = phoxy.ForwardDownload;
      phoxy.ForwardDownload = function()
      {
        var args = arguments;
        args[0] = "/web/operblock/" + args[0];
        old_download.apply(this, args);
      }
//debugger;
      var old_ajax = phoxy.AJAX;
      phoxy.AJAX = function()
      {
        var args = arguments;
//          args[0] = "/web/operblock/api/" + args[0];
        old_ajax.apply(this, args);
      }
    }
    operblock.PatchDoctorRoom();
  }
};

operblock.PatchDoctorRoom = function()
{
  if (!phoxy.config)
    return setTimeout(arguments.callee, 50);

  var target = $('.core_menu_mlm')
    .first()
    .find("ul")
    .first();

  if (!target.size())
    return setTimeout(arguments.callee, 50);
  console.log("Operblock doctor_room patched");
  var trigger =  phoxy.DeferRender('start_button', {});

  target
    .append
    (
      $('<li></li>').html(trigger)
    );
};

operblock.Load =  function()
{
  operblock.AutoloadOperblock();
};

operblock.RenderInto = function( obj, cb )
{
  //$(".doctorroom-resizePanel").first().click();
  var ret = phoxy.DeferRender('main', undefined, cb);
  $(obj).html(ret);
  return ret;
};

operblock.LevrachRender = function( obj, patient_id )
{
  var res = operblock.RenderInto(obj);
  if (!patient_id)
    return;

    phoxy.Appeared('#patient_content', function()
    {
      $(this).html
      (
        phoxy.DeferRender('levrach/bootstrap', 'patient/IdByIB?id=' + patient_id)
      );
    });
    
  return;
  function Do()
  {
    if (typeof phoxy.Appeared == 'undefined')
      return setTimeout(Do, 50);

    phoxy.Appeared('#patient_content', function()
    {
      $(this).html
      (
        phoxy.DeferRender('levrach/bootstrap', 'patient/IdByIB?id=' + patient_id)
      );
    });
  };

  Do();
};

operblock.TryToExtractIB = function()
{
  var html = $('.nomer_event').html();
  if (!html)
    return null;
  return html.split(' ')[1];
};

setTimeout(function()
{
  operblock.Load();
}, 500);