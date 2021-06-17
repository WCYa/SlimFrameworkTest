<?php

namespace App\Controllers;

use PDO;
use PDOException;
use Exception;

class AuthController extends Controller
{
    public function loginView($request, $response)
    {
        if ($this->auth->check()) 
            return $response->withRedirect($this->router->pathFor('home'));
        return $this->view->render($response, 'auth/login.twig');
    }

    public function login($request, $response)
    {
        $auth = $this->attempt(
            $request->getParam('account'),
            $request->getParam('password')
        );

        if ($auth === true) {
            // Identify browser to Redirect user to home page
            $agent = $_SERVER['HTTP_USER_AGENT'];
            if (strpos($agent, "MSIE 8.0") || strpos($agent, "MSIE 7.0") || strpos($agent, "MSIE 6.0")) 
            {  // old IE
                return $this->render404($response);
            } else {
                $this->flash->addMessage('success', '# 歡迎登入');
                return $response->withRedirect($this->container->router->pathFor('home'));
            }
        } else {
            $this->setError($auth);
            return $response->withRedirect($this->container->router->pathFor('login'));
        }

    } // end of function index

    public function attempt($account, $password)
    {
        $error = [];
        $account = trim($account);
        $password = trim($password);
        if (!$account) {
            $error['account'] = "不可空白.";
        }
        if (!$password) {
            $error['password'] = "不可空白.";
        }
        try {
            if ($error) {
                throw new Exception();
            } else {
                $sql = "SELECT * FROM users WHERE account = :account";
                $pdo_statement = $this->db->prepare($sql);
                $pdo_statement->execute([
                    'account' => $account
                ]);
                $results = $pdo_statement->fetchAll(PDO::FETCH_ASSOC);
                if (count($results) > 0) {
                    $result = $results[0];
                    $hashed_password = $result['password'];
                    if (password_verify($password, $hashed_password)) {
                        // login success
                        // Store data in session variables
                        $this->setSession(
                            $result["id"], 
                            $result["account"], 
                            $result["username"], 
                            $result["group_code"],
                            $result["role"],
                            $result["user_no"]
                        );
                        return true;
                    } else {
                        $error['password'] = "密碼錯誤.";
                        throw new Exception();
                    }
                } else {
                    $error['account'] = "無此帳號.";
                    throw new Exception();
                }
            }
        } catch (PDOException $e) {
            die('!!! 發生錯誤，請稍後再試');
        } catch (Exception $e) {
            return $error;
        }
    }

    public function logout($request, $response)
    {
        $_SESSION = array();
        $this->flash->addMessage('info', '# 成功登出');
        return $response->withRedirect($this->container->router->pathFor('login'));
    }

    public function resetPasswordView($request, $response)
    {
        return $this->view->render($response, 'auth/reset_password.twig');
    }

    public function resetPassword($request, $response)
    {
        $auth = $this->resetPasswdHandler(
            $request->getParam('oPasswd'),
            $request->getParam('nPasswd'),
            $request->getParam('nnPasswd')
        );

        if ($auth === true) {
            $this->flash->addMessage('success', '# 重設密碼成功');
            return $response->withRedirect($this->container->router->pathFor('reset-password'));
        } else {
            $this->setError($auth);
            return $response->withRedirect($this->container->router->pathFor('reset-password'));
        }
    }

    public function resetPasswdHandler($oPasswd, $nPasswd, $nnPasswd)
    {
        $error = [];
        $oPasswd = trim($oPasswd);
        $nPasswd = trim($nPasswd);
        $nnPasswd = trim($nnPasswd);
        if (!$oPasswd)
            $error['oPasswd'] = "不可空白";
        if (!$nPasswd)
            $error['nPasswd'] = "不可空白";
        if (!$nnPasswd)
            $error['nnPasswd'] = "不可空白";
        if ($nPasswd !== $nnPasswd)
            $error['nnPasswd'] .= " ,與前次輸入的密碼不相同";
        try {

            if ($error) {
                throw new Exception();
            } else {
                $id = $this->auth->getId();
                $sql = "SELECT * FROM users WHERE id = :id";
                $pdo_statement = $this->db->prepare($sql);
                $pdo_statement->execute([
                    'id' => $id
                ]);
                $results = $pdo_statement->fetchAll(PDO::FETCH_ASSOC);
                if (count($results) > 0) {
                    $result = $results[0];
                    $hashed_password = $result['password'];
                    if (password_verify($oPasswd, $hashed_password)) {
                        $nPasswd = password_hash($nPasswd, PASSWORD_DEFAULT);
                        $sql = "UPDATE users SET password = :password WHERE id = :id";
                        $pdo_stmt = $this->container->db->prepare($sql);
                        $pdo_stmt->execute([
                            'password' => $nPasswd,
                            'id' => $id
                        ]);
                        return true;
                    } else {
                        $error['oPasswd'] = "舊密碼錯誤.";
                        throw new Exception();
                    }
                } else {
                    throw new PDOException();
                }
            }

        } catch (PDOException $e) {
            die('!!! 發生錯誤，請稍後再試<br />' . $e->getMessage());
        } catch (Exception $e) {
            return $error;
        }
    }

}