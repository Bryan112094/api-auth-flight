<?php
require 'vendor/autoload.php';
require 'class/Users.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$users = new Users();


//Todos los datos
Flight::route('GET /users', [$users, 'selectAll']);

//Dato especifico
Flight::route('GET /users/@id', [$users, 'selectOne']);

//Auth
Flight::route('POST /auth', [$users, 'auth']);

//Agregar datos
Flight::route('POST /users', [$users, 'insert']);

//Actualizar Datos
Flight::route('PUT /users', [$users, 'update']);

//Eliminar Dato
Flight::route('DELETE /users', [$users, 'delete']);

Flight::start();