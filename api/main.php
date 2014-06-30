<?php

class main extends api
{
  protected function Reserve()
  {
    return
    [
      "design" => "body",
      "script" => "utils.js"
    ];
  }

  protected function Home()
  {
    return
    [
      "design" => "content_fork",
      "result" => "content"
    ];
  }
}
