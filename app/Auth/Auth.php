<?php

namespace App\Auth;

use Interop\Container\ContainerInterface;
use \PDO;
use \PDOException;
use \Exception;

class Auth
{   
    protected $container;
    
    public function __construct(ContainerInterface $c)
    {
        $this->container = $c;
    }

    public function __get($property)
    {
        if ($this->container->{$property}) {
            return $this->container->{$property};
        }
    }

    public function check()
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        return true;
    }

    public function getId()
    {
        if (isset($_SESSION['user_id']))
            return $_SESSION['user_id'];
    }

    public function getAccount()
    {
        if (isset($_SESSION['account']))
            return trim($_SESSION['account']);
    }

    public function getUser()
    {
        if (isset($_SESSION['username']))
            return trim($_SESSION['username']);
    }

    public function getGroup()
    {
        if (isset($_SESSION['group_code']))
            return trim($_SESSION['group_code']);
    }

    public function getUserNo()
    {
        if (isset($_SESSION['user_no']))
            return trim($_SESSION['user_no']);
    }

    public function isAdmin()
    {   
        if (isset($_SESSION['role']))
            if ($_SESSION['role'] === 'admin')
                return true;
        return false;
    }

    public function checkAuth($page, $action)
    {
        $pdo_stmt = $this->container->db->prepare("SELECT authority FROM users WHERE id = :id");
        $pdo_stmt->execute([ 'id' => $this->getId() ]);
        $results = $pdo_stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($results) > 0) {
            $auth = json_decode($results[0]["authority"], true);
            if (isset($auth[$page]) && in_array($action, $auth[$page]))
                return true;
        }
        return false;
    }
}