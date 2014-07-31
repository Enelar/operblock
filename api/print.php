<?php

class print extends api
{
  protected function Reserve( $ejs )
  {
    global $_POST;
    return
    [
      "design" => $ejs,
      "data" => $_POST;
    ];
  }
}