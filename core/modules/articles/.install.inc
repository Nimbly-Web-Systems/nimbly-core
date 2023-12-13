<?php

load_library("data");

$result = data_create_resource("articles", [
  "fields"=> [
    "title"=> [
      "name"=> "Title",
      "required" => true,
      "type" => "name",
      "slug"=> true //creates slug in title_slug
    ],
    
    "published"=> [
      "name"=> "published",
      "type"=> "boolean"
    ],
    "date"=> [
      "name"=> "date",
      "type"=> "date",
    ],
    "intro"=> [
      "name"=> "Intro",
      "type"=> "html"
    ],
    "main_text"=> [
      "name"=> "Main text",
      "type"=> "html",
      "admin_col"=> false
    ],
    "main_img" => [
      "name" => "Image",
      "type" => "image"
    ],
    "tags"=> [
      "name"=> "Tags",
      "type"=> "text",
      "admin_col" => false
    ]
  ],
  "pk" => "title_slug",
  "sort"=> [
    "field"=> "date",
    "flags"=> "string",
    "order"=> "desc"
  ]
]);

$route = 'article/(slug)';
$result &= data_exists(".routes", md5($route)) 
	|| data_create(".routes", md5($route), ["route" => $route, "order" => 200]);

return $result;