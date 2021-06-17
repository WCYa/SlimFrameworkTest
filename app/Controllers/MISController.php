<?php

namespace App\Controllers;

use PDO;
use PDOException;
use Exception;

class MISController extends Controller
{
    public function registerView($request, $response)
    {
        $pdo_stmt = $this->container->db->query("SELECT * FROM groups");
        $groups = $pdo_stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->view->render($response, 'mis/register.twig', compact('groups'));
    }

    public function register($request, $response)
    {
        $error =[];
        $account = trim($request->getParam('account'));
        $password = trim($request->getParam('password'));
        $password2 = trim($request->getParam('password2'));
        $name = trim($request->getParam('name'));
        $email = trim($request->getParam('email'));
        $group_code = trim($request->getParam('group_code'));
        $role = trim($request->getParam('role'));

        if (!$account)
            $error['account'] = "不可空白";
        if (!$password)
            $error['password'] = "不可空白";
        if (!$password2)
            $error['password2'] = "不可空白";
        if (!$name)
            $error['name'] = "不可空白";
        if ($password !== $password2)
            $error['password2'] .= " ,與前次輸入密碼不同";

        if ($error) {
            $this->setError($error);
            return $response->withRedirect($this->container->router->pathFor('register'));
        } else {
            $password = password_hash($password, PASSWORD_DEFAULT);
            try {
                $sql = "INSERT INTO users(account, password, username, email, group_code, role ) 
                VALUE(:account, :password, :name, :email, :group_code, :role )";
                $pdo_stmt = $this->container->db->prepare($sql);
                $pdo_stmt->execute([
                    'account' => $account,
                    'password' => $password,
                    'name' => $name,
                    'email' => $email,
                    'group_code' => $group_code,
                    'role' => $role
                ]);
                $this->container->flash->addMessage('success', '# 使用者新增成功');
                $this->unsetOld();
                return $response->withRedirect($this->container->router->pathFor('register'));
            } catch (PDOException $e) {
                $this->container->flash->addMessage('error', '# 發生錯誤，請稍後在試' . $e->getMessage());
                return $response->withRedirect($this->container->router->pathFor('register'));
            }
        }
    }

    public function setPasswordView($request, $response)
    {
        $pdo_stmt = $this->container->db->query("SELECT account, username FROM users ORDER BY account");
        $users = $pdo_stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->container->view->render($response, 'mis/set_password.twig', compact('users'));
    }

    public function setPassword($request, $response)
    {
        $error = [];
        $account = trim($request->getParam('account'));
        $nPasswd = trim($request->getParam('nPasswd'));
        $nnPasswd = trim($request->getParam('nnPasswd'));

        if (!$account)
            $error['account'] = "需選擇帳號";
        if (!$nPasswd)
            $error['nPasswd'] = "不可空白";
        if (!$nnPasswd)
            $error['nnPasswd'] = "不可空白";
        if ($nPasswd !== $nnPasswd)
            $error['nnPasswd'] .= " ,與前次密碼輸入不相同";
        try{
            if ($error) {
                throw new Exception();
            } else {
                
                $sql = "SELECT account FROM users WHERE account = :account";
                $pdo_stmt = $this->container->db->prepare($sql);
                $pdo_stmt->execute([
                    'account' => $account
                ]);
                $results = $pdo_stmt->fetchAll(PDO::FETCH_OBJ);
                if (count($results) < 1) {
                    $error['account'] = "無此帳號";
                    throw new Exception();
                }

                $sql = "UPDATE users SET password = :password WHERE account = :account";
                $pdo_stmt = $this->container->db->prepare($sql);
                $pdo_stmt->execute([
                    'password' => password_hash($nPasswd, PASSWORD_DEFAULT),
                    'account' => $account
                ]);
                $this->container->flash->addMessage('success', '帳號 : ' . $account . ' 密碼設定成功');
                return $response->withRedirect($this->container->router->pathFor('set-password'));
            }
        } catch (PDOException $e) {
            die('發生錯誤，請稍後再試' . $e->getMessage());
        } catch (Exception $e) {
            $this->setError($error);
            return $response->withRedirect($this->container->router->pathFor('set-password'));
        }
    }

    public function modifyUserProfileView($request, $response)
    {
        $pdo_stmt = $this->container->db->query("SELECT account, username FROM users ORDER BY account");
        $users = $pdo_stmt->fetchAll(PDO::FETCH_ASSOC);
        $pdo_stmt = $this->container->db->query("SELECT * FROM groups");
        $groups = $pdo_stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->container->view->render($response, 'mis/modify_user_profile.twig', compact('users', 'groups'));
    }

    public function modifyUserProfile($request, $response)
    {
        $error = [];
        $account = trim($request->getParam('account'));
        $username = trim($request->getParam('username'));
        $email = trim($request->getParam('email'));
        $group_code = trim($request->getParam('group_code'));
        $role = trim($request->getParam('role'));
        $auth_array = $request->getParam('auth');
        $authority = json_encode($request->getParam('auth'), JSON_UNESCAPED_UNICODE);
        $user_no = "";

        try {
            $sql = "SELECT account FROM users WHERE account = :account";
            $pdo_stmt = $this->container->db->prepare($sql);
            $pdo_stmt->execute([ 'account' => $account ]);
            $results = $pdo_stmt->fetchAll(PDO::FETCH_OBJ);
            if ( count($results) < 1 ) {
                $error['account'] = '此帳號不存在';
                throw new Exception();
            } else {
                $sql = "UPDATE users SET username = :username, email = :email, group_code = :group_code, role = :role, authority = :authority 
                WHERE account = :account";
                $pdo_stmt = $this->container->db->prepare($sql);
                $pdo_stmt->execute([
                    'username' => $username,
                    'email' => $email,
                    'group_code' => $group_code,
                    'role' => $role,
                    'authority' => $authority,
                    'account' => $account
                ]);
                $this->setSession(
                    $this->auth->getId(),
                    $account, 
                    $username,
                    $group_code,
                    $role,
                    $user_no
                );
                $this->container->flash->addMessage('success', '修改會員資料成功');
                $this->unsetOld();
            }
        } catch (PDOException $e) {
            die("發生錯誤，請稍後再試.");
        } catch (Exception $e) {
            $this->setError($error);
        }

        return $response->withRedirect($this->container->router->pathFor('modify-user-profile'));
    }

    public function userProfileJson($request, $response)
    {
        try {
            $user = false;
            $account = trim($request->getParam('account'));
            $sql = "SELECT account, username, email, group_code, role, grade, bDeleted, authority FROM users WHERE account=:account";
            $pdo_stmt = $this->container->db->prepare($sql);
            $pdo_stmt->execute([
                'account' => $account
            ]);
            $user = $pdo_stmt->fetch(PDO::FETCH_OBJ);
            $user->authority = json_decode($user->authority, true);
            return $response->withJson($user, 200);
        } catch (PDOException $e) {
            return $response->withJson($user, 500);
        }
    }

}