<?php
// http://www.youlikeprogramming.com/2013/01/interfacing-postgresqls-hstore-with-php/
function pgArrayToPhp($text)
{
  if(is_null($text))
    return [];
  if(!is_string($text) && $text == '{}')
    return [];

  $text = substr($text, 1, -1); // Removes starting "{" and ending "}"
  if(substr($text, 0, 1) == '"')
    $text = substr($text, 1);

  if(substr($text, -1, 1) == '"')
    $text = substr($text, 0, -1);

  // If double quotes are present, we know we're working with a string.
  if(strstr($text, '"')) // Assuming string array.
    $values = explode('","', $text);
  else // Assuming Integer array.
    $values = explode(',', $text);

  $fixed_values = [];

  foreach ($values as $value)
    $fixed_values[] = str_replace('\\"', '"', $value);

  return $fixed_values;
}

function pgArrayFromPhp($array, $data_type = 'character varying')
{
  $array = (array) $array; // Type cast to array.
  $result = [];
  
  foreach($array as $entry)
  { // Iterate through array.
    if(is_array($entry)) // Supports nested arrays.
      $result[] = pgArrayFromPhp($entry);
    else
    {
      $entry = str_replace('"', '\\"', $entry); // Escape double-quotes.
      $entry = pg_escape_string($entry); // Escape everything else.
      $result[] = '"' . $entry . '"';
    }
  }

  $ret = '{' . implode(',', $result) . '}';
  if ($data_type !== null)
    $ret .= '::' . $data_type . '[]'; // format
  return $ret;
}
