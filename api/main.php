<?php

class main extends api
{
  protected function Reserve()
  {
    return
    [
      "design" => "body",
      "script" => ["approve.js", "utils.js", "cool_hack.js"],
      "before" => "ClearCanvas"
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
