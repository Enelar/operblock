<?php
$ejs = json_encode(array_shift($_GET));
if ($_SERVER['REQUEST_METHOD'] == "POST")
  $json = json_encode($_POST);
else
  $json = json_encode($_GET);
?>
<script data-main="phoxy" src="phoxy/libs/require.js" type="text/javascript"></script>
<script type="text/javascript">
var context = <?php echo $json; ?>;
var ejs = <?php echo $ejs; ?>;
(function()
{ 
  if (typeof phoxy == 'undefined')
    return setTimeout(arguments.callee, 100);
  if (typeof phoxy.DeferRender == 'undefined')
    return setTimeout(arguments.callee, 100);

  $('body').append
  (
    phoxy.DeferRender('print/body', context, function()
    {
      $('body').append
      (
        phoxy.DeferRender(ejs, context, function()
        {
          phoxy.Defer(function() { window.print(); }, 100);
        })
      );    
    })
  );
  
})();
</script>