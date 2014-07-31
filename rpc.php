<?php
include_once('utils.php');
error_reporting(E_ALL); ini_set('display_errors', 1);

include_once('phpsql/phpsql.php');
include_once('phpsql/mysql.php');
$sql = new phpsql();
$my = $sql->Connect("mysql://dbuser:dbpassword@localhost/max");

include_once('phpsql/db.php');
db::Bind($my);

ini_set('default_charset', 'utf-8');
db::Query("SET NAMES 'utf8'");

function phoxy_conf()
{
  $ret = phoxy_default_conf();
  $ret["cache_global"] = "1h";
  $ret["cache_global"] = "1s"; // debug
  // I _HAVE_ to code in that style.
  $ret["js_prefix"] = "/web/operblock/js/";
  $ret["js_dir"] = "";
  $ret["api_dir"] = "api";
//  $ret["api_prefix"] = "/web/operblock/";
  //ret["ejs_dir"] = "/web/operblock/ejs/";
  return $ret;
}

include_once('phoxy/index.php');
