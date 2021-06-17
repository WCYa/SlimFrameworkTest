<?php

namespace App\Controllers;

use Interop\Container\ContainerInterface;
use PDO;
use DateTime;

abstract class Controller 
{
    protected $container;
    
    public function __construct (ContainerInterface $c )
    {
        $this->container = $c;
    }

    public function __get($property)
    {
        if ($this->container->{$property}) {
            return $this->container->{$property};
        }
    }
    
    public function render404($response)
    {
        return $this->container->view->render($response->withStatus(404), 'errors/404.twig');
    }

    public function setError($error_array)
    {
        $_SESSION['error'] = $error_array;
    }

    public function unsetOld()
    {
        unset($_SESSION['old']);
    }

    /*
        @param $id  int
        @param $account string
        @param $username    string
        @param $group_code int
        @param $authority array
    */
    public function setSession($id, $account, $username, $group_code, $role, $user_no)
    {
        $_SESSION["user_id"] = $id;
        $_SESSION["account"] = $account;
        $_SESSION["username"] = $username;
        $_SESSION["group_code"] = $group_code;
        $_SESSION{"role"} = $role;
        $_SESSION{"user_no"} = $user_no;
    }

    /*
     * 日期驗證
     */
    public function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    /*
     *   
     */
    public function exitJson($msg)
    {
        echo json_encode(array('msg' => $msg), JSON_UNESCAPED_UNICODE);
        exit;
    }
}