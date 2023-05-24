<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
require 'vendor/autoload.php';

class Users {
    private $db;
    function __construct(){
        Flight::register('db', 'PDO', array('mysql:host='.$_ENV['DB_HOST'].';dbname='.$_ENV['DB_NAME'].'',$_ENV['DB_USER'],$_ENV['DB_PASSWORD']), function($db){
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        });
        $this->db = Flight::db();
    }

    function getToken(){
        $headers = apache_request_headers();
        if(!isset($headers["Authorization"])){
            Flight::halt(403, json_encode([
                'error' => 'Unauthenticated required',
                'status' => 'error'
            ]));
        }
        $authorization = $headers["Authorization"];
        $authorizationArray = explode(" ", $authorization);
        $token = $authorizationArray[1];
        try {
            return JWT::decode($token, new Key($_ENV['JWT_SECRET_KEY'], 'HS256'));
        } catch (\Throwable $th) {
            Flight::halt(403, json_encode([
                'error' => $th->getMessage(),
                'status' => 'error'
            ]));
        }
    }
    
    function validateToken(){
        $info = $this->getToken();
        $db = Flight::db();
        $query = $db->prepare("SELECT * FROM usuario WHERE id = :id");
        $query->execute([':id' => $info->data]);
        $rows = $query->fetchColumn();
        return $rows;
    }
    
    function auth(){
        $pass = Flight::request()->data->pass;
        $email = Flight::request()->data->email;
        $query = $this->db->prepare("SELECT * FROM usuario WHERE correo = :email AND password = :pass");
        $array = [
            "error" => "No se pudo validad su identidad por favor, intenta más tarde",
            "status" => "error"
        ];
        if($query->execute([':email' => $email, ':pass' => $pass])){
            $user = $query->fetch();
            $now = strtotime('now');
            $key = $_ENV['JWT_SECRET_KEY'];
            $payload = [
                'exp' => $now + 3600,
                'data' => $user['id']
            ];
        
            $jwt = JWT::encode($payload, $key, 'HS256');
            $array = [
                "token" => $jwt
            ];
        }
       
        Flight::json($array);
    
    }

    function selectAll($page){
        if(!isset($page)){
            $page = 1;
        }
        $query = $this->db->prepare("SELECT * FROM usuario");
        $query->execute();
        $total = $query->rowCount();
        $total_per_page = 10;
        $pages = ceil($total / $total_per_page);
        if($total < 1){
            Flight::halt(204, json_encode([
                "error" => "No hay contenido para mostrar",
                "status" => "error"
            ]));
        }
        if($page > $pages || $page < 1){
            Flight::halt(404, json_encode([
                "error" => "La petición es incorrecta",
                "status" => "error"
            ]));
        }
        $start_record = ($page - 1) * $total_per_page;
        $query2 = $this->db->prepare("SELECT * FROM usuario LIMIT $start_record, $total_per_page");
        $query2->execute();
        $data = $query2->fetchAll();
        $array = [];
        foreach ($data as $row){
            $array[] = [
                "id" => $row['id'],
                "name" => $row['nombre'],
                "email" => $row['correo'],
                "phone" => $row['telefono'],
                "status" => $row['status']
            ];
        }
        Flight::json([
            "Total" => $total,
            "page" => $page,
            "total_page" => $pages,
            "rows" => $array
        ]);
    }

    function selectOne($id){
        $query = $this->db->prepare("SELECT * FROM usuario WHERE id = :id");
        $query->execute([':id' => $id]);
        $data = $query->fetch();
        $array[] = [
            "id" => $data['id'],
            "name" => $data['nombre'],
            "email" => $data['correo'],
            "phone" => $data['telefono'],
            "status" => $data['status']
        ];
        
        Flight::json($array);
    }

    function insert(){
        if(!$this->validateToken()){
            Flight::halt(403, json_encode([
                'error' => 'Unauthorization',
                'status' => 'error'
            ]));
        }
        //Datos de Formulario
        //$name = Flight::request()->query->name;
        //$email = Flight::request()->query->email;
        //$phone = Flight::request()->query->phone;
        //$pass = Flight::request()->query->pass;
        //Datos de JSON
        $name = Flight::request()->data->name;
        $email = Flight::request()->data->email;
        $phone = Flight::request()->data->phone;
        $pass = Flight::request()->data->pass;
        $query = $this->db->prepare("INSERT INTO usuario (nombre, correo, telefono, password) VALUES (:name, :email, :phone, :pass)");
    
        $array = [
            "error" => "Hubo un error al agregar los registros, por favor intenta más tarde",
            "status" => "error"
        ];
        if($query->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':pass' => $pass])){
            $array = [
                "data" => [
                    "id" => $this->db->lastInsertId(),
                    "name" => $name,
                    "email" => $email,
                    "phone" => $phone 
                ],
                "status" => "success"
            ];
        }
       
        Flight::json($array);
    }

    function update(){
        if(!$this->validateToken()){
            Flight::halt(403, json_encode([
                'error' => 'Unauthorization',
                'status' => 'error'
            ]));
        }
        $db = Flight::db();
        $id = Flight::request()->data->id;
        $name = Flight::request()->data->name;
        $email = Flight::request()->data->email;
        $phone = Flight::request()->data->phone;
        $pass = Flight::request()->data->pass;
        $query = $db->prepare("UPDATE usuario SET nombre = :name, correo = :email, telefono = :phone, password = :pass WHERE id = :id");
    
        $array = [
            "error" => "Hubo un error al agregar los registros, por favor intenta más tarde",
            "status" => "error"
        ];
        if($query->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':pass' => $pass, ':id' => $id])){
            $array = [
                "data" => [
                    "id" => $id,
                    "name" => $name,
                    "email" => $email,
                    "phone" => $phone 
                ],
                "status" => "success"
            ];
        }
       
        Flight::json($array);
    }

    function delete(){
        if(!$this->validateToken()){
            Flight::halt(403, json_encode([
                'error' => 'Unauthorization',
                'status' => 'error'
            ]));
        }
        $id = Flight::request()->data->id;
        $query = $this->db->prepare("DELETE FROM usuario WHERE id = :id");
    
        $array = [
            "error" => "Hubo un error al agregar los registros, por favor intenta más tarde",
            "status" => "error"
        ];
        if($query->execute([':id' => $id])){
            $array = [
                "data" => [
                    "id" => $id,
                ],
                "status" => "success"
            ];
        }
       
        Flight::json($array);
    }
}
?>