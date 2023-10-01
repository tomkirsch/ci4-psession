<?php

$routes->add('/', 'Home::index');
$routes->add('/home/randompage/(:any)', 'Home::randompage/$1');
