<?php

namespace App\Controllers;

use App\Controllers\Controller;
use PDO;
use PDOException;
use Exception;
use Slim\Http\UploadedFile;

class OfficialDocController extends Controller
{
    public function launchView($request, $response)
    {
        $rows = $this->db->query("SELECT * FROM groups")->fetchAll(PDO::FETCH_OBJ);
        foreach ($rows as $row) {
            $groups[$row->code] = $row->group_name;
        }
        $rows = $this->db->query("SELECT id, username, group_code FROM users")->fetchAll(PDO::FETCH_OBJ);
        foreach ($rows as $row) {
            $users[$row->id] = [ $row->username, $row->group_code ];
        }
        $groups = json_encode($groups, JSON_UNESCAPED_UNICODE);
        $users = json_encode($users, JSON_UNESCAPED_UNICODE);
        $this->container->view->render($response, 'official_doc/launch.twig', compact('groups', 'users'));
    }

    /*
        發起新流程
    */
    public function launch($request, $response)
    {
        try {
            if (empty(trim($request->getParam('title')))) {
                throw new Exception('標題不可空白');
            }
            $slot_num = $request->getParam('slot_num');
            for ($i=1; $i<=$slot_num; $i++) {
                if (empty(trim($request->getParam('slot' . $i))))
                    throw new Exception('會簽人' . $i . ' 不可空白');
            }
            $upload_result = $this->filterAndMoveUploadedFile($request->getUploadedFiles());
            if ($upload_result === false) 
                throw new Exception('檔案上傳失敗');

            $pdo = $this->db;
            $pdo->beginTransaction();
        
            $sql_statement = "INSERT INTO official_doc 
            (title, sender_id, cur_sign_user_id, attachment_path, comment, slot_num) 
            VALUE(:title, :sender_id, :cur_sign_user_id, :attachment_path, :comment, :slot_num)";
            
            $pdo_statement = $pdo->prepare($sql_statement);
            $pdo_statement->execute([
                'title' => $request->getParam('title'),
                'sender_id' => $this->auth->getId(),
                'cur_sign_user_id' => $request->getParam('slot1'),
                'attachment_path' => $upload_result,
                'comment' => $request->getParam('comment'),
                'slot_num' => $slot_num
            ]);

            $data_id = $pdo->lastInsertId();

            $sql_statement = "INSERT INTO official_doc_flow(data_id, user_id, slot_no, state) 
            VALUE(:data_id, :user_id, :slot_no, :state)";
            $pdo_statement = $pdo->prepare($sql_statement);

            for ($i=1; $i<=$slot_num; $i++) {
                $pdo_statement->execute([
                    'data_id' => $data_id,
                    'user_id' => $request->getParam('slot' . $i),
                    'slot_no' => $i,
                    'state' => 0
                ]);
            }

            $pdo->commit();
            $this->container->flash->addMessage('info', '新增成功, 流程 ID 為: ' . $data_id);
            $this->unsetOld();
            return $response->withRedirect($this->router->pathFor('official-doc.launch'));
        
        } catch (PDOException $e) {
            $this->flash->addMessage('error', 'Failed to Insert flow_general ' . $e->getMessage());
            return $response->withRedirect($this->router->pathFor('official-doc.launch'));
        } catch (Exception $e) {
            $this->flash->addMessage('error', $e->getMessage());
            return $response->withRedirect($this->router->pathFor('official-doc.launch'));
        }
        
    }

    /* 
        過濾上傳的檔案 
    */
    public function filterAndMoveUploadedFile($uploadedFiles)
    {
        
        $directory = $this->container->get('upload_directory') . '/flow_general';
        $extensions = ['xls', 'xlsx', 'pdf', 'doc', 'docx', 'txt', 'jpg', 'gif', 'jpeg', 'zip'];

        $uploadedFile = $uploadedFiles['attachment'];
        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
            $filename = $uploadedFile->getClientFilename(); // 獲取檔案名稱
            $extension = pathinfo($filename, PATHINFO_EXTENSION);   // 取副檔名
            // 判斷是否是允許的副檔名
            if (!in_array($extension, $extensions)) {
                $this->container->flash->addMessage('error', "附件 $filename 副檔名不允許");
                return false;
            }
            // 判斷檔案大小
            $size = $uploadedFile->getSize();
            if ($size > 10000000) {
                $this->container->flash->addMessage('error', "檔案大小超過 10M : " . $filename);
                return false;
            }
            if (($filename = $this->moveUploadedFile($directory, $uploadedFile)) === false) {
                $this->container->flash->addMessage('error', "上傳失敗");
                return false;
            }
            
/*
            $smbDir = '/mnt/pc0111n' . '/flow_general/' . $uploadedFile->getClientFilename();
            // # setsebool httpd_use_cifs
            if (!file_exists('/mnt/pc0111n' . '/flow_general/'))
                mkdir('/mnt/pc0111n' . '/flow_general/');
            copy($filepath, $smbDir);
*/
            return $filename;
        }
    }


    /* 
        將暫存區的上傳檔案移至伺服器實際目錄 
    */
    public function moveUploadedFile($directory, UploadedFile $uploadedFile)
    {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
       
        for ($n = 0; $n < 100; $n++) {
            $basename = bin2hex(random_bytes(8));
            $filename = sprintf('%s.%0.8s', $basename, $extension);
            $file = $directory . DIRECTORY_SEPARATOR . $filename;
            if (!file_exists($file)) {
                $uploadedFile->moveTo($file);
                return $filename;
            }
        }
        return false;
    }
    

    /* 
        測試 
    */
    public function withJson($request, $response)
    {
        $data = [];
        $responseStatus = 200;
        return $response->withJson($data, $responseStatus);
        // withJson 等於下面
        /*
        return $response->withHeader('Content-Type','application/json')
            ->withStatus($responseStatus)
            ->write(json_encode($data));
        */
    }


    /*
        已 json 格式回傳流程資訊 
    */
    public function dataJson($request, $response)
    {
        $limit = $request->getParam('limit');
        $offset = $request->getParam('offset');
        $limit = isset($limit) ? intval($limit) : 10;
        $offset = isset($offset) ? intval($offset) : 0;
        $option = $request->getParam('option');
        if ($option !== 'finish') $option == 'notyet';

        $pdo = $this->db;
        try {
            if ($option === 'notyet') {
                $sql_stmt = "SELECT d.title, d.id, d.sender_id, d.cur_sign_user_id, d.create_at, d.state, u.username, g.group_name  
                FROM official_doc d, users u, groups g 
                WHERE 
                ( 
                    d.cur_sign_user_id = :user_id1 
                    OR  d.sender_id = :user_id2 
                ) 
                AND d.state IN (0,2) 
                AND u.id = d.sender_id 
                AND u.group_code = g.code 
                ORDER BY d.id DESC 
                LIMIT :limit OFFSET :offset";
                $pdo_stmt = $pdo->prepare($sql_stmt);
                $pdo_stmt->execute([
                    'user_id1' => $this->auth->getId(),
                    'user_id2' => $this->auth->getId(),
                    'limit' => $limit,
                    'offset' => $offset
                ]);

                $pdo_stmt->execute();
                $results = $pdo_stmt->fetchAll(PDO::FETCH_OBJ);

                foreach ($results as $result) {
                    $btn = '';
                    if ($result->cur_sign_user_id == $this->auth->getId())
                        $btn .= '<a class="btn btn-primary" href="' . 
                        $this->router->pathFor("official-doc.check", ["data_id" => $result->id]) . 
                        '">簽核</a>';
                    else if ($result->sender_id == $this->auth->getId() && $result->state == 0)
                        $btn = '<a class="btn btn-outline-warning" href="' . 
                        $this->router->pathFor("official-doc.detail", ["data_id" => $result->id]) . 
                        '">檢閱</a>';
                    else if ($result->sender_id == $this->auth->getId() && $result->state == 2)
                        $btn = '<a class="btn btn-danger" href="' . 
                        $this->router->pathFor("official-doc.detail", ["data_id" => $result->id]) . 
                        '">退回</a>';
                    
                    $data[] = [
                        'btn' => $btn,
                        'title' => $result->title,
                        'sender' => $result->username, 
                        'create_at' => $result->create_at,
                        'group' => $result->group_name
                    ];
                }
                return $response->withJson(array(
                    'total' => count($results),
                    'rows' => $data
                ), 200);
            } else if ($option === 'finish') {
                $sql_stmt = "SELECT d.title, d.id, d.sender_id, d.create_at, u.username, g.group_name  
                FROM official_doc d, users u, groups g 
                WHERE 
                d.sender_id = :user_id
                AND d.state = 1 
                AND u.id = d.sender_id 
                AND u.group_code = g.code 
                ORDER BY d.id DESC 
                LIMIT :limit OFFSET :offset";
                $pdo_stmt = $pdo->prepare($sql_stmt);
                $pdo_stmt->execute([
                    'user_id' => $this->auth->getId(),
                    'limit' => $limit,
                    'offset' => $offset
                ]);

                $pdo_stmt->execute();
                $results = $pdo_stmt->fetchAll(PDO::FETCH_OBJ);

                foreach ($results as $result) {
                    $btn = '<a class="btn btn-outline-success" href="' . 
                        $this->router->pathFor("official-doc.detail", ["data_id" => $result->id]) . 
                        '">檢閱</a>';
                    
                    $data[] = [
                        'btn' => $btn,
                        'title' => $result->title,
                        'sender' => $result->username, 
                        'create_at' => $result->create_at,
                        'group' => $result->group_name
                    ];
                }
                return $response->withJson(array(
                    'total' => count($results),
                    'rows' => $data
                ), 200);
            }
            
        } catch (PDOException $e) {
            return $response->withJson(array('error' => $e->getMessage()), 500);
        }
    }


    /*
        詳細資料畫面
    */
    public function detailView($request, $response, $args)
    {
        $pdo = $this->container->db;
        try {
            $sql_stmt = "SELECT * FROM official_doc o, users u WHERE o.id = :id";
            $pdo_stmt = $pdo->prepare($sql_stmt);
            $pdo_stmt->execute(['id' => $args['data_id']]);
            $result = $pdo_stmt->fetch(PDO::FETCH_OBJ);
            if ($result->sender_id !== $this->container->auth->getId())
                throw new Exception();

            $sql_stmt = "SELECT * FROM official_doc_flow f, users u 
            WHERE data_id = :id AND f.user_id = u.id  
            ORDER BY slot_no";
            $pdo_stmt = $pdo->prepare($sql_stmt);
            $pdo_stmt->execute(['id' => $args['data_id']]);
            $results_node = $pdo_stmt->fetchAll(PDO::FETCH_OBJ);

            $pdo_stmt = $pdo->query("SELECT username FROM users WHERE id = {$result->sender_id}")->fetch(PDO::FETCH_OBJ);
            $username = $pdo_stmt->username;

            $finished = '';
            if ($result->state === 1) $finished = "<span class='text-danger'>(已簽核)</span>";
            
            $data = [ 
                'data_id' => $args['data_id'],
                'title' => $result->title,
                'create_at' => $result->create_at,
                'username' => $username,
                'comment' => $result->comment,
                'attachment_path' => $result->attachment_path,
                'flows' => $results_node,
                'finished' => $finished
             ];
            
            return $this->view->render($response, 'official_doc/detail.twig', compact('data'));

        } catch (PDOException $e) {
            return $this->view->render($response, 'errors/404.twig');
        } catch (Exception $e) {
            return $this->view->render($response, 'errors/404.twig');
        }

    }

    /*
        簽核畫面
    */
    public function checkView($request, $response, $args)
    {
        
        $pdo = $this->container->db;
        try {
            $sql_stmt = "SELECT * FROM official_doc o, users u WHERE o.id = :id";
            $pdo_stmt = $pdo->prepare($sql_stmt);
            $pdo_stmt->execute(['id' => $args['data_id']]);
            $result = $pdo_stmt->fetch(PDO::FETCH_OBJ);
            if ($result->cur_sign_user_id !== $this->container->auth->getId())
                throw new Exception();

            $sql_stmt = "SELECT * FROM official_doc_flow f, users u 
            WHERE data_id = :id AND f.user_id = u.id  
            ORDER BY slot_no";
            $pdo_stmt = $pdo->prepare($sql_stmt);
            $pdo_stmt->execute(['id' => $args['data_id']]);
            $results_node = $pdo_stmt->fetchAll(PDO::FETCH_OBJ);

            
            $pdo_stmt = $pdo->query("SELECT username FROM users WHERE id = {$result->sender_id}")->fetch(PDO::FETCH_OBJ);
            $username = $pdo_stmt->username;
            
            $data = [ 
                'data_id' => $args['data_id'],
                'title' => $result->title,
                'create_at' => $result->create_at,
                'username' => $username,
                'comment' => $result->comment,
                'attachment_path' => $result->attachment_path,
                'flows' => $results_node
             ];
            
            return $this->view->render($response, 'official_doc/check_doc.twig', compact('data'));

        } catch (PDOException $e) {
            return $this->view->render($response, 'errors/404.twig');
        } catch (Exception $e) {
            return $this->view->render($response, 'errors/404.twig');
        }
    }

    public function checkDoc($request, $response)
    {
        $pdo = $this->container->db;
        $user_id = $this->container->auth->getId();
        $data_id = $request->getParam('data_id');

        $comment = null !== $request->getParam('comment') ? trim($request->getParam('comment')) : '';
        $state = $request->getParam('docAction');
        $state = ($state === 'confirm') ? 1 : 2;
        $end_at = '';
        
        try {
            $sql_stmt = "SELECT * FROM official_doc WHERE id = :id";
            $pdo_stmt = $pdo->prepare($sql_stmt);
            $pdo_stmt->execute([ ':id' => $data_id ]);
            $doc = $pdo_stmt->fetch(PDO::FETCH_OBJ);
            if ($doc->cur_sign_user_id !== $user_id)
                throw new Exception();
                
            $pdo->beginTransaction();
            
            // 更新流程詳細資料
            $sql = "UPDATE official_doc_flow SET comment = :comment, state = :state  
            WHERE data_id = :data_id AND user_id = :user_id";
            $pdo_stmt = $pdo->prepare($sql);
            $pdo_stmt->execute([
                ':comment' => $comment,
                ':state' => $state,
                ':data_id' => $data_id,
                ':user_id' => $user_id
            ]);
            
            // 更新流程資訊
            // 查詢下一位簽核者
            if ($state === 1) {
                if ($doc->cur_slot >= $doc->slot_num) {
                    // 結案
                    $sql = "UPDATE official_doc SET end_at = CURRENT_TIMESTAMP(), state=1 
                    WHERE id = :id";
                    $pdo_stmt = $pdo->prepare($sql);
                    $pdo_stmt->execute([ ':id' => $data_id ]);
                } else {
                    // 未結案 下一位進入流程
                    $sql = "SELECT user_id FROM official_doc_flow 
                    WHERE data_id = :data_id AND slot_no = :slot_no";
                    $pdo_stmt = $pdo->prepare($sql);
                    $pdo_stmt->execute([ ':data_id' => $data_id, ':slot_no' => ($doc->cur_slot + 1) ]);
                    $next = $pdo_stmt->fetch(PDO::FETCH_OBJ);

                    $sql = "UPDATE official_doc SET cur_sign_user_id = :cur_sign_user_id, cur_slot = cur_slot + 1, state = 0 
                    WHERE id = :id";
                    $pdo_stmt = $pdo->prepare($sql);
                    $pdo_stmt->execute([
                        ':cur_sign_user_id' => $next->user_id,
                        ':id' => $data_id
                        ]);
                }
                $this->container->flash->addMessage('info', '簽核成功');
            } else if ($state === 2) {
                // 未結案 退件
                $sql = "UPDATE official_doc SET state=2 
                WHERE id = :id";
                $pdo_stmt = $pdo->prepare($sql);
                $pdo_stmt->execute([ ':id' => $data_id ]);
                $this->container->flash->addMessage('info', '退件成功');
            }
            
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollback();
            $this->container->flash->addMessage('error', '發生錯誤，請稍後再試');
        } catch (Exception $e) {
            return $this->view->render($response, 'errors/404.twig');
        }
        return $response->withRedirect($this->container->router->pathFor('home'));
    } // end of public function checkDoc
    
    public function download($request, $response, $args)
    {
        $filename = $args['file_name'];
        $directory = $this->container->get('upload_directory') . '/flow_general';
        $file = $directory . '/' . $filename;
        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment;filename="'.basename($file).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }
    }
}