var hackx1 = 0;
function ClearCanvas(data, cb)
{
  phoxy.loaded = true;
  if (hackx1)
    return cb();
  hackx1++;

  phoxy.AJAX('user/GroupName', function(data)
  {
    var group = data.data.GroupName;
    if (group != 'levrach')
    {
      var defer_render_code = $(".doctorroom-contentPanel").first().html();
      $('body').first().html(defer_render_code).addClass(group);
    }
    cb();
  })
}