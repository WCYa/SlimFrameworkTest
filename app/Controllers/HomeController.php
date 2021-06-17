<?php

namespace App\Controllers;

use PDO;
use PDOException;

class HomeController extends Controller
{
    public function index($request, $response)
    {   
        return $this->container->view->render($response, 'home.twig');
    }

    public function getGroupUsers($request, $response)
    {
        if (!$request->getParam('group_code'))
            return $response->withJson(array(), 500);
        try {
            $sql_stmt = "SELECT username,id FROM users WHERE group_code=:group_code";
            $pdo_stmt = $this->db->prepare($sql_stmt);
            $pdo_stmt->execute([ 'group_code' => $request->getParam('group_code') ]);
            $users = $pdo_stmt->fetchAll();
            return $response->withJson($users, 200);
        } catch (PDOException $e) {
            return $response->withJson(array(), 500);
        }
    }
    
}