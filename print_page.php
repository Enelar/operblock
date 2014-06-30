<?php
$ejs = json_encode(end($_GET));
$json = json_encode($_POST);
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

  $('body').append(phoxy.DeferRender('print/body', context));
  $('body').append(phoxy.DeferRender(ejs, context));
})();
</script>