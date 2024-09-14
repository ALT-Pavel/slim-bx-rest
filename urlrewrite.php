<?php
$arUrlRewrite=array (
  0 => 
  array (
    'CONDITION' => '#^\\/?\\/mobileapp/jn\\/(.*)\\/.*#',
    'RULE' => 'componentName=$1',
    'ID' => NULL,
    'PATH' => '/bitrix/services/mobileapp/jn.php',
    'SORT' => 100,
  ),
  2 => 
  array (
    'CONDITION' => '#^/bitrix/services/ymarket/#',
    'RULE' => '',
    'ID' => '',
    'PATH' => '/bitrix/services/ymarket/index.php',
    'SORT' => 100,
  ),
  5 => 
  array (
    'CONDITION' => '#^/e-store/books/reviews/#',
    'RULE' => '',
    'ID' => 'bitrix:news',
    'PATH' => '/e-store/books/reviews/index.php',
    'SORT' => 100,
  ),
  4 => 
  array (
    'CONDITION' => '#^/e-store/books/#',
    'RULE' => '',
    'ID' => 'bitrix:catalog',
    'PATH' => '/e-store/books/index.php',
    'SORT' => 100,
  ),
  3 => 
  array (
    'CONDITION' => '#^/content/news/#',
    'RULE' => '',
    'ID' => 'bitrix:news',
    'PATH' => '/content/news/index.php',
    'SORT' => 100,
  ),
  1 => 
  array (
    'CONDITION' => '#^/rest/#',
    'RULE' => '',
    'ID' => NULL,
    'PATH' => '/bitrix/services/rest/index.php',
    'SORT' => 100,
  ),
  6 => 
  array (
    'CONDITION' => '#^/api/#',
		'RULE'      => '',
		'ID'        => null,
		'PATH'      => '/local/api/index.php',
		'SORT'      => 100,
  ),
);
