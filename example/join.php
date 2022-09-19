<?php

require 'db_config.php';
require '../vendor/autoload.php';

require 'Models/User.php';

use Example\Models\User;

/*
 * INNER JOIN
 */

$users = (new User())
    ->find(null, null, 'users.*, addresses.city')
    ->join('addresses', 'addresses.user_id', '=', 'users.id')
    ->first();

if ($users) {
    var_dump($users);
}

/*
 * LEFT/RIGHT JOIN
 */
$users = (new User())
    ->find(null, null, 'users.*, addresses.city')
    ->join('addresses', 'addresses.user_id', '=', 'users.id', 'left')
    ->join('addresses', 'addresses.user_id', '=', 'users.id', 'left')
    ->get();

if ($users) {
    var_dump($users);
}

/*
 * WHERE
 */
$users = (new User())
    ->select('*')
    ->join('addresses', 'addresses.user_id', '=', 'users.id', 'inner')
    ->where('addresses.city', '=' 'São Paulo');

if ($users) {
    var_dump($users);
}
