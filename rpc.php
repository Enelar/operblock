<?php
include_once('utils.php');
error_reporting(E_ALL); ini_set('display_errors', 1);

include_once('phpsql/phpsql.php');
include_once('phpsql/pgsql.php');
$sql = new phpsql();
$pg = $sql->Connect("pgsql://postgres@localhost/vista");

include_once('phpsql/db.php');
db::Bind($pg);

function phoxy_conf()
{
  $ret = phoxy_default_conf();
  $ret["cache_global"] = "1h";
  return $ret;
}

include_once('phoxy/index.php');
