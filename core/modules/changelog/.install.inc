<?php

load_library("data");

$result = data_create_resource(".changelog", [
  "fields"=> [
    "date"=> [
      "name"=> "date",
      "type"=> "date",
    ],
    "change"=> [
      "name"=> "Change descripition",
      "type"=> "html",
      "buttons" => "bold,italic,anchor,quote,orderedlist,unorderedlist",
      "multiple" => true
    ]
  ],
  "sort"=> [
    "field"=> "date",
    "flags"=> "string",
    "order"=> "desc"
  ]
]);

return $result;