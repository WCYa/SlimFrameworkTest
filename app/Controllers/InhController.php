<?php

namespace App\Controllers;

use PDO;
use PDOException;
use Exception;
use TCPDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

class InhController extends Controller
{
    public function showView($request, $response)
    {
        /*
        if (!$this->container->auth->checkAuth('inh', 'inhr')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }
        */
        $arr = $this->data($request, 'show');
        $results = isset($arr['results']) ? $arr['results'] : null;
        $offset = isset($arr['offset']) ? $arr['offset'] : null;
        $count = isset($arr['count']) ? $arr['count'] : null;
        $error = isset($arr['error']) ? $arr['error'] : null;
        $this->container->view->render($response, 'inh/show.twig', [
            'menu_inh' => 'show',
            'error' => $error,
            'results' => $results,
            'count' => $count,
            'offset' => $offset,
            'old' => $request->getParams(),
            'date' => date('Ymd')
        ]);
    }

    public function inhView($request, $response)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhw')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }
        $this->container->view->render($response, 'inh/inh.twig', [
            'menu_inh' => 'show'
        ]);
    }

    public function input($request, $response)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhw')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }
        
        $id_serial = null !== $request->getParam('id_serial') ? trim($request->getParam('id_serial')) : '';
        $tmp = null !== $request->getParam('tmp') ? trim($request->getParam('tmp')) : '';
        
        if ( empty($id_serial) ) $this->exitJson('系統辨識碼錯誤');
        if (empty($tmp) || !ctype_alnum($tmp) || (strpos($tmp, 'tmp') === false)) {
            $this->exitJson('臨時資料表錯誤');
        }
        
        $man_lot1 = null !== $request->getParam('man_lot1') ? trim($request->getParam('man_lot1')) : '';
        $inh_qty1 = null !== $request->getParam('inh_qty1') ? trim($request->getParam('inh_qty1')) : '0';
        $man_lot2 = null !== $request->getParam('man_lot2') ? trim($request->getParam('man_lot2')) : '';
        $inh_qty2 = null !== $request->getParam('inh_qty2') ? trim($request->getParam('inh_qty2')) : '0';
        $man_lot3 = null !== $request->getParam('man_lot3') ? trim($request->getParam('man_lot3')) : '';
        $inh_qty3 = null !== $request->getParam('inh_qty3') ? trim($request->getParam('inh_qty3')) : '0';
        $no_inh_qty = null !== $request->getParam('no_inh_qty') ? trim($request->getParam('no_inh_qty')) : '0';

        $pdo = $this->container->pdb;

        // 寫入前再判斷尚未繳庫數是否正確
        $inhed_qty = 0; #已列印數
        $sql = "SELECT inh_qty1, inh_qty2, inh_qty3 
                FROM inh_prn_mst 
                WHERE id_serial = :id_serial AND handle_fg = 0 ;";

        $pdo_stmt = $pdo->prepare($sql);
        $pdo_stmt->execute([
            ':id_serial' => $id_serial
        ]);
        $inhqty_rows = $pdo_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($inhqty_rows as $inhqty) {
            $inhed_qty += ($inhqty['inh_qty1'] + $inhqty['inh_qty2'] + $inhqty['inh_qty3']);
        }
        if ( ($inh_qty1 + $inh_qty2 + $inh_qty3 + $inhed_qty) > $no_inh_qty ) { $this->exitJson('錯誤:繳庫數大於未繳庫數' . $no_inh_qty); }
        
        $pdo->beginTransaction();
        $sql = "SELECT serial_no FROM serial_no 
                WHERE yy=TO_CHAR(LOCALTIMESTAMP,'YYYY')  
                ORDER BY serial_no DESC FOR UPDATE NOWAIT";
                
        $db_rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // 有當年份資料
        if (isset($db_rows) && count($db_rows) > 0 ) {
            $row = $db_rows[0];
            if ($row['serial_no'] >= 99999) { 
                $pdo->rollBack();
                $this->exitJson('超過當年份繳庫編號最大值');
            }
            $no = $row['serial_no'] + 1 ;
            $sql = "SELECT serial_no FROM serial_no 
                    WHERE yy=TO_CHAR(LOCALTIMESTAMP,'YYYY') 
                    AND mm=TO_CHAR(LOCALTIMESTAMP,'MM');";

            $db_rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            // 有當年當月份資料
            if (isset($db_rows) && count($db_rows) > 0 ) {
                $sql = "UPDATE serial_no SET serial_no = :serial_no, e_date = LOCALTIMESTAMP 
                        WHERE yy = TO_CHAR(LOCALTIMESTAMP, 'YYYY') 
                        AND mm = TO_CHAR(LOCALTIMESTAMP, 'MM');";
                $pdo_stmt = $pdo->prepare($sql);
                $pdo_stmt->execute([
                    ':serial_no' => $no
                ]);
            } else { // 無當年當月份資料
                $sql = "INSERT INTO serial_no(yy, mm, serial_no, s_date, e_date) 
                        VALUES(TO_CHAR(LOCALTIMESTAMP,'YYYY'), TO_CHAR(LOCALTIMESTAMP,'MM')
                        , ?, LOCALTIMESTAMP, LOCALTIMESTAMP);";
                $pdo_stmt = $pdo->prepare($sql);
                $pdo_stmt->execute([$no]);

            }
            
        } else { // 無當年份資料
            $sql = "INSERT INTO serial_no(yy, mm, serial_no, s_date, e_date) 
                    VALUES(TO_CHAR(LOCALTIMESTAMP, 'YYYY'), TO_CHAR(LOCALTIMESTAMP,'MM')
                    , 1, LOCALTIMESTAMP, LOCALTIMESTAMP);";
            $pdo->exec($sql);
            
        }
        
        $year = date('Y');
        $serial_no = ($year % 100) * 100000 + $no;
        $sql = "SELECT * FROM {$tmp} WHERE id_serial = ?;";
        $pdo_stmt = $pdo->prepare($sql);
        $pdo_stmt->execute([$id_serial]);
        $db_rows = $pdo_stmt->fetchAll(PDO::FETCH_ASSOC);
        $row = $db_rows[0];
        
        $part_no = trim($row['part_no']);
        $part_name = trim($row['part_name']);
        $unit = trim($row['unit']);
        $lot_no = trim($row['lot_no']);
                
        $sql = "INSERT INTO inh_prn_mst(part_no, part_no_new,
                part_name, sec_code, line_name, inh_dep, inh_code, hos_code, plan_date,
                lot_no, unit, id_serial, plan_qty, isu_qty, no_inh_qty, user_no, serial_no,
                inh_date, man_lot1, inh_qty1, man_lot2, inh_qty2, man_lot3, inh_qty3,
                s_date, e_date, print_times, handle_fg, isu_serial)
                VALUES(?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, CURRENT_DATE, ?, ?, 
                ?, ?, ?, ?, LOCALTIMESTAMP,
                LOCALTIMESTAMP, '1', '0', ?) ;";
        $pdo_stmt = $pdo->prepare($sql);

        $value = [
            $part_no,
            trim($row['part_no_new']),
            $part_name,
            trim($row['sec_code']),
            trim($row['line_name']),
            $this->container->auth->getGroup(),
            trim($row['inh_code']),
            trim($row['hos_code']),
            trim($row['plan_date']),
            $lot_no,
            $unit,
            $id_serial,
            intval($row['plan_qty']),
            intval($row['isu_qty']),
            intval($row['no_inh_qty']),
            $this->container->auth->getUserNo(),
            $serial_no,
            $man_lot1,
            intval($inh_qty1),
            $man_lot2,
            intval($inh_qty2),
            $man_lot3,
            intval($inh_qty3),
            trim($row['isu_serial'])
        ];

        
        
        $pdo_stmt->execute($value);

        $pdo->commit();

        return $response->withJson([
            'msg' => 'ok',
            'serial_no' => $serial_no,
            'lot_no' => $lot_no,
            'part_no' => $part_no,
            'part_name' => $part_name,
            'unit' => $unit
        ]);

    }

    public function inhWorkJson($request, $response)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhr')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }
        $search1 = null !== $request->getParam('search1') ? trim($request->getParam('search1')) : '';
        $search2 = null !== $request->getParam('search2') ? strtoupper(trim($request->getParam('search2'))) : '';
        $search1_cahce = null !== $request->getParam('search1_cahce') ? trim($request->getParam('search1_cahce')) : '';
        $search2_cahce = null !== $request->getParam('search2_cahce') ? strtoupper(trim($request->getParam('search2_cahce'))) : '';
        $tmp = null !== $request->getParam('tmp') ? trim($request->getParam('tmp')) : '';
        
        if ( (empty($search1) && empty($search2)) || empty($tmp) ) {
            die('沒有搜尋條件或臨時資料表');
        } else {
            if ((!ctype_digit($search1) && !empty($search1)) || 
                (!preg_match('/^[a-zA-Z0-9-]+$/', $search2) && !empty($search2)) || 
                !ctype_alnum($tmp)) {
                    die('條件驗證發生錯誤 s1 s2 tmp');
            }
            if (strpos($tmp, 'tmp') === false) { die('暫存表名稱錯誤'); }

            $pdo = $this->container->pdb;
            
            if ( ($search1!==$search1_cahce) || ($search2!==$search2_cahce) ) {
                $sql = "SELECT * FROM prg_parameter WHERE name='db_inh01';";
                $prgList = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
                if(!isset($prgList) || count($prgList) < 1) die('沒有程式設定資料');
                $prg = $prgList[0];
                $prgPath = trim($prg['path']);
                $prgPara1 = trim($prg['para1']);
                $prgPara2 = trim($prg['para2']);
                if ( $prgPara2 != "2" && $prgPara2 != "1" ) die('程式參數para2設定錯誤，範圍必須在:1~2');
                
                $sql = "SELECT sys_exec('" . $prgPath . 
                    " -alias:" .  $this->container['settings']['pdb']['dbname'] .
                    " -action:" . $prgPara1 .
                    " -output:" . $tmp .
                    " -no:" . $search1 .
                    " -part_no:" . $search2 . "');";
                
                $pdo->exec($sql);
            }
            $db_rows = $pdo->query("SELECT COUNT(*) FROM {$tmp};")->fetchAll(PDO::FETCH_ASSOC);
            $total = $db_rows[0]['count'];
            
            $limit = null !== $request->getParam('limit') ? trim($request->getParam('limit')) : '10';
            $offset = null !== $request->getParam('offset') ? trim($request->getParam('offset')) : '0';

            if (!ctype_digit($limit) || !ctype_digit($offset)) {
                die('條件驗證發生錯誤 limit offset');
            }
            
            $sql = "SELECT * FROM {$tmp} LIMIT :limit OFFSET :offset ;";
            $pdo_stmt = $pdo->prepare($sql);
            $pdo_stmt->execute([
                ':limit' => intval($limit),
                ':offset' => intval($offset)
            ]);
            $db_rows = $pdo_stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = [];
            foreach($db_rows as $row) {
                $inhed_qty = 0; #已列印數
                $sql = "SELECT inh_qty1, inh_qty2 ,inh_qty3 
                        FROM inh_prn_mst 
                        WHERE id_serial = :id_serial AND handle_fg = 0 ;";
                $pdo_stmt = $pdo->prepare($sql);
                $pdo_stmt->execute([
                    ':id_serial' => $row['id_serial']
                ]);
                $inhqty_rows = $pdo_stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach($inhqty_rows as $inhqty) {
                    $inhed_qty += ($inhqty['inh_qty1'] + $inhqty['inh_qty2'] + $inhqty['inh_qty3']);
                }
                
                $data[] = [
                    'rec_id' => $row['rec_id'],
                    'part_no' => $row['part_no'],
                    'part_no_new' => $row['part_no_new'],
                    'part_name' => $row['part_name'],
                    'sec_code' => $row['sec_code'],
                    'line_name' => $row['line_name'],
                    'inh_dep' => $row['inh_dep'],
                    'inh_code' => $row['inh_code'],
                    'plan_date' => $row['plan_date'],
                    'lot_no' => $row['lot_no'],
                    'isu_serial' => $row['isu_serial'],
                    'unit' => $row['unit'],
                    'id_serial' => $row['id_serial'],
                    'plan_qty' => $row['plan_qty'],
                    'isu_qty' => $row['isu_qty'],
                    'no_inh_qty' => $row['no_inh_qty'],
                    'user_no' => $row['user_no'],
                    'serial_no' => $row['serial_no'],
                    'inh_date' => $row['inh_date'],
                    'inh_qty1' => $row['inh_qty1'],
                    'man_lot1' => $row['man_lot1'],
                    'inh_qty2' => $row['rec_id'],
                    'man_lot2' => $row['inh_qty2'],
                    'inh_qty3' => $row['inh_qty3'],
                    'man_lot3' => $row['man_lot3'],
                    'handle_fg' => $row['handle_fg'],
                    'inhed_qty' => $inhed_qty
                ];
            
            }
            
            return $response->withJson([
                'total' => $total,
                'rows' => $data
                ]);

        }
    }

    public function getTemp($request, $response)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhw')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }
        try {
            $pdo = $this->container->pdb;
            # 清除兩天前人在使用中的臨時表
            $day = date("Y-m-d", strtotime('-2 day'));
            $sql = "SELECT tmp_name, use_date FROM tmp_list WHERE use_status='使用中' AND use_date < '{$day}'; ";
            $db_rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            foreach($db_rows as $row) {
                $sql = "UPDATE tmp_list SET use_status='' WHERE tmp_name= :tmp_name;";
                $pdo_stmt = $pdo->prepare($sql);
                $pdo_stmt->execute([
                    ':tmp_name' => $row['tmp_name']
                ]);
                // 確認資料表名稱有 tmp 關鍵字 
                if(strpos($row['tmp_name'], 'tmp') !== false) {
                    $sql = "DROP TABLE IF EXISTS " . $row['tmp_name'];
                    $pdo->query($sql);
                }
            }
            
            $pdo->beginTransaction();
            $sql = "SELECT * FROM tmp_list WHERE use_status!='使用中' OR use_status IS NULL FOR UPDATE NOWAIT;";
            $db_rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            if(!isset($db_rows) || count($db_rows) < 1) {
                $pdo->rollBack();
                return $response->withJson(['msg' => '無可使用的臨時表'], 500);
            }

            $tmp = $db_rows[0]['tmp_name'];
            
            $sql = "UPDATE tmp_list SET use_status='使用中', use_date=LOCALTIMESTAMP, use_name = :use_name WHERE tmp_name=:tmp_name ;";
            $pdo_stmt = $pdo->prepare($sql);
            $pdo_stmt->execute([
                ':use_name' => $this->container->auth->getUser(),
                ':tmp_name' => $tmp
            ]);
            $pdo->commit();
            return $response->withJson([ 'tmp' => $tmp ], 200);
        } catch (PDOException $e) {
            return $response->withJson(['msg' => '資料錯誤'], 500);
        }
    }

    public function delTemp($request, $response)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhw')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }
        try {
            $tmp = null !== $request->getParam('tmp') ? trim($request->getParam('tmp')) : '';
            if(empty($tmp) || strpos($tmp, 'tmp') === false) 
                return $response->withJson(array(), 500);
            
            
            $sql = "UPDATE tmp_list SET use_status='' WHERE tmp_name = :tmp_name";
            $pdo_stmt = $this->container->pdb->prepare($sql);
            $pdo_stmt->execute([
                ':tmp_name' => $tmp
            ]);
            $sql = "DROP TABLE IF EXISTS " . $tmp;
            $this->container->pdb->query($sql);
        } catch (PDOException $e) {
            die('delTemp Error.');
        }
    }

    public function details($request, $response, $args)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhr')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }
        $type = $args['type'];
        $arr = $this->data($request,  $type);
        if (isset($arr['error']))
            return die('資料發生錯誤');

        $date1 = $request->getParam('date1');
        $date2 = $request->getParam('date2');
        $db_rows = (array)$arr['results'];
        $body = $response->getBody();

        if ($type === 'txt') {
            $filename = "inh_{$date1}_{$date2}.txt";
            $no = 0;
            foreach($db_rows as $row) {
                $no++;
                $body->write(
                    sprintf("%4d|%-9s|%-22s|%-32s|%-5s| %-8s|%-8s|%-9s|%-8s|%-9s |%-8s|%-9s|%-8s|%-12s|\r\n",
                    $no, $row['serial_no'], $row['part_no'], $row['part_name'], $row['inh_dep'],
                    $row['lot_no'], $row['isu_qty'], $row['man_lot1'], $row['inh_qty1'], $row['man_lot2'],
                    $row['inh_qty2'], $row['man_lot3'], $row['inh_qty3'], date('Y-m-d H:i:s', strtotime($row['e_date'])))
                );
            }
        } else if ($type === 'csv') {
            $filename = "inh_{$date1}_{$date2}.csv";
            $no = 0;
            $body->write("序,最後列印日,繳庫編號,倉庫收,轉物管,組別,件號,件名,批號,需求數,製號1,數量1,製號2,數量2,製號3,數量3 \r\n");
            foreach($db_rows as $row) {
                $no++;
                $part_name = str_replace('"', '""', $row['part_name']);
                $body->write(
                    $no . ',' .
                    date('Y-m-d H:i:s', strtotime($row['e_date'])) . ',' .
                    '="' . $row['serial_no'] . '",' .
                    ",". 
                    ",". 
                    $row['inh_dep'] . ',' .
                    $row['part_no'] . ',' .
                    '"' . $part_name . '",' .
                    '="' . $row['lot_no'] . '",' .
                    $row['isu_qty'] . ',' .
                    '="' . $row['man_lot1'] . '",' .
                    $row['inh_qty1'] . ',' .
                    '="' . $row['man_lot2'] . '",' .
                    $row['inh_qty2'] . ',' .
                    '="' . $row['man_lot3'] . '",' .
                    $row['inh_qty3'] . ',' . "\r\n" 
                );
            }
        }
        $response = $response->withHeader('Pragma', 'Public');
        $response = $response->withHeader('Expires', '0');
        $response = $response->withHeader('Control', 'must-revalidate, post-check=0, pre-check=0');
        $response = $response->withAddedHeader('Cache-Control', 'private');
        $response = $response->withHeader('Content-Type', 'application/download');
        $response = $response->withHeader('Content-Disposition', "attachment; filename={$filename};");
        $response = $response->withHeader('Content-Transfer-Encoding', 'binary');

        return $response;
    }

    public function outputUtf8($filename)
    {
        $tmp = explode('.', $filename);
        $extension = end($tmp);
        if($extension == 'csv'){
            // office 2010 以上才知道 開啟使用 utf-8 開啟
            // 否則需要開啟新 excel 匯入文字檔 使用csv 檔匯入
            echo chr(0xEF).chr(0xBB).chr(0xBF);
        }
    }
    
    public function utf8ToBig5($str) 
    {
        return iconv("UTF-8", "BIG5//IGNORE", $str);
    }

    public function detailsXLS($request, $response)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhr')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }

        $arr = $this->data($request,  'xls');
        if (isset($arr['error']))
            return die('資料發生錯誤');

        $date1 = $request->getParam('date1');
        $date2 = $request->getParam('date2');
        $db_rows = (array)$arr['results'];
        $filename = "inh_{$date1}_{$date2}";
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Rename worksheet
        $sheet->setTitle('繳庫單資料');
        // 寫入標題
        $sheet->setCellValueByColumnAndRow(1, 1, '序');
        $sheet->setCellValueByColumnAndRow(2, 1, '最後列印日');
        $sheet->setCellValueByColumnAndRow(3, 1, '繳庫編號');
        $sheet->setCellValueByColumnAndRow(4, 1, '倉庫收');
        $sheet->setCellValueByColumnAndRow(5, 1, '轉物管');
        $sheet->setCellValueByColumnAndRow(6, 1, '組別');
        $sheet->setCellValueByColumnAndRow(7, 1, '件號');
        $sheet->setCellValueByColumnAndRow(8, 1, '件名');
        $sheet->setCellValueByColumnAndRow(9, 1, '批號');
        $sheet->setCellValueByColumnAndRow(10, 1, '需求數');
        $sheet->setCellValueByColumnAndRow(11, 1, '製號1');
        $sheet->setCellValueByColumnAndRow(12, 1, '數量1');
        $sheet->setCellValueByColumnAndRow(13, 1, '製號2');
        $sheet->setCellValueByColumnAndRow(14, 1, '數量2');
        $sheet->setCellValueByColumnAndRow(15, 1, '製號3');
        $sheet->setCellValueByColumnAndRow(16, 1, '數量3');

        // 寫入資料
        $no = 0;
        foreach($db_rows as $row) {
            $no++;
            $row_num = $no + 1;
            $sheet->setCellValueByColumnAndRow(1, $row_num, $no);
            $sheet->setCellValueByColumnAndRow(2, $row_num, date('Y-m-d H:i:s', strtotime($row['e_date'])));
            $sheet->setCellValueByColumnAndRow(3, $row_num, $row['serial_no']);
            //$sheet->setCellValueByColumnAndRow(4, $row_num, '');
            //$sheet->setCellValueByColumnAndRow(5, $row_num, '');
            $sheet->setCellValueByColumnAndRow(6, $row_num, $row['inh_dep']);
            $sheet->setCellValueByColumnAndRow(7, $row_num, $row['part_no']);
            $sheet->setCellValueByColumnAndRow(8, $row_num, $row['part_name']);
            $sheet->setCellValueByColumnAndRow(9, $row_num, $row['lot_no']);
            $sheet->setCellValueByColumnAndRow(10, $row_num, $row['isu_qty']);
            $sheet->setCellValueByColumnAndRow(11, $row_num, $row['man_lot1']);
            $sheet->setCellValueByColumnAndRow(12, $row_num, $row['inh_qty1']);
            $sheet->setCellValueByColumnAndRow(13, $row_num, $row['man_lot2']);
            $sheet->setCellValueByColumnAndRow(14, $row_num, $row['inh_qty2']);
            $sheet->setCellValueByColumnAndRow(15, $row_num, $row['man_lot3']);
            $sheet->setCellValueByColumnAndRow(16, $row_num, $row['inh_qty3']);
        }

        // Redirect output to a client’s web browser (Xls)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="'.$filename.'.xls"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $writer = IOFactory::createWriter($spreadsheet, 'Xls');
        $writer->save('php://output');
        exit;

    }

    public function detailsXLSX($request, $response)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhr')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }

        $arr = $this->data($request,  'xls');
        if (isset($arr['error']))
            return die('資料發生錯誤');
        $results = $arr['results'];

        $date1 = $request->getParam('date1');
        $date2 = $request->getParam('date2');
        $filename = "inh_{$date1}_{$date2}";

        $this->container->view->render($response, 'inh/detailsXLSX.twig', [
            'db_rows' => $results,
            'filename' => $filename
        ]);
    }

    public function data($request, $page='')
    {
        $error = [];
        // 日期
        $date1 = null !== $request->getParam('date1') ? trim($request->getParam('date1')) : date('Ymd');
        $date2 = null !== $request->getParam('date2') ? trim($request->getParam('date2')) : date('Ymd');
        if ( !empty($date1) && !$this->validateDate($date1, 'Ymd') && !$this->validateDate($date1, 'Y-m-d') )
            $error['date1'] = "日期格式錯誤";
        if ( !empty($date2) && !$this->validateDate($date2, 'Ymd') && !$this->validateDate($date2, 'Y-m-d') )
            $error['date2'] = "日期格式錯誤";
        
        // 可搜尋與排序的欄位
        $columns = array('serial_no', 'isu_serial', 'part_no', 'lot_no', 'inh_dep');
        // 排序
        $order1 = null !== $request->getParam('order1') ? trim($request->getParam('order1')) : 'serial_no';
        $order2 = null !== $request->getParam('order2') ? trim($request->getParam('order2')) : '';
        if (!in_array($order1, $columns))
            $error['order1'] = "排序 1 資料錯誤";
        if (!empty($order2) && !in_array($order2, $columns))
            $error['order2'] = "排序 2 資料錯誤";
        $power1 = null !== $request->getParam('power1') ? trim($request->getParam('power1')) : 'ASC';
        $power2 = null !== $request->getParam('power2') ? trim($request->getParam('power2')) : 'ASC';
        if ($power1 !== 'ASC' && $power1 !== 'DESC')
            $power1 = 'ASC';
        if ($power2 !== 'ASC' && $power2 !== 'DESC')
            $power2 = 'ASC';

        // 條件
        $key1 = null !== $request->getParam('key1') ? trim($request->getParam('key1')) : 'serial_no';
        $condition1 = null !== $request->getParam('condition1') ? trim($request->getParam('condition1')) : '';
        $key2 = null !== $request->getParam('key2') ? trim($request->getParam('key2')) : 'serial_no';
        $condition2 = null !== $request->getParam('condition2') ? trim($request->getParam('condition2')) : '';

        if (!(in_array($key1, $columns) && in_array($key2, $columns)))
            $error['key'] = '條件資料錯誤';

        // 組別
        $group = null !== $request->getParam('group') ? trim($request->getParam('group')) : '';
        if($group != '358' && $group != '360') {
            $group = '';
        }

        // offset
        $offset = null !== $request->getParam('offset') ? trim($request->getParam('offset')) : 0;
        if (!ctype_digit($offset))
            $offset = 0;
        $offset = intval($offset);
        $limit = null !== $request->getParam('limit') ? trim($request->getParam('limit')) : 100;
        if (!ctype_digit($limit))
            $limit = 100;
        $limit = intval($limit);

        // status
        $status = null !== $request->getParam('select_status') ? trim($request->getParam('select_status')) : '';
        
        // DB
        if (!$error) {
            try {
                $sql_group = $sql_key1 = $sql_key2 = 
                $sql_date1 = $sql_date2 = $sql_order2 = $sql_status = '';
                $value_array = array();
                $value_array['limit'] = $limit;
                $value_array['offset'] = $offset;

                if (!empty($date1)) {
                    $sql_date1 = "AND inh_date >= :date1";
                    $value_array['date1'] = $date1;
                }
                
                if (!empty($date2)) {
                    $sql_date2 = "AND inh_date <= :date2";
                    $value_array['date2'] = $date2;
                }
                
                if (!empty($group)) {
                    $sql_group = "AND inh_dep = :group";
                    $value_array['group'] = $group;
                }
                
                if (!empty($condition1)) {
                    if ($key1 === 'serial_no') {
                        if (!ctype_digit($condition1)) {
                            $error['condition1'] = "繳庫單編號為數字";
                            throw new Exception();
                        }
                        $sql_key1 = "AND serial_no = :condition1";
                        $value_array['condition1'] = $condition1;
                    } else {
                        $sql_key1 = "AND {$key1} LIKE :condition1";
                        $value_array['condition1'] = strtoupper($condition1) . '%';
                    }
                }
                
                if (!empty($condition2)) {
                    if ($key2 === 'serial_no') {
                        if (!ctype_digit($condition2)) {
                            $error['condition1'] = "繳庫單編號為數字";
                            throw new Exception();
                        }
                        $sql_key2 = "AND serial_no = :condition2";
                        $value_array['condition2'] = $condition2;
                    } else {
                        $sql_key2 = "AND {$key2} LIKE :condition2";
                        $value_array['condition2'] = strtoupper($condition2) . '%';
                    }
                }
                
                if (!empty($order2))
                    $sql_order2 = ",{$order2} {$power2}";

                if (!empty($status)) {
                    $sql_status = "AND handle_fg = :handle_fg";
                    $value_array['handle_fg'] = $status;
                }

                if ($page == 'show') {
                    $sql = "SELECT * FROM inh_prn_mst WHERE 1=1 {$sql_status} {$sql_date1} {$sql_date2} {$sql_group} {$sql_key1} {$sql_key2} 
                    ORDER BY {$order1} {$power1} {$sql_order2} 
                    LIMIT :limit OFFSET :offset";
                    $pdo_stmt = $this->container->pdb->prepare($sql);
                    $pdo_stmt->execute($value_array);
                    $results = $pdo_stmt->fetchAll(PDO::FETCH_ASSOC);

                    $sql = "SELECT COUNT(*) FROM inh_prn_mst WHERE 1=1 {$sql_status}  {$sql_date1} {$sql_date2} {$sql_group} {$sql_key1} {$sql_key2}";
                    $pdo_stmt = $this->container->pdb->prepare($sql);
                    unset($value_array['limit']);
                    unset($value_array['offset']);
                    $pdo_stmt->execute($value_array);
                    $result = $pdo_stmt->fetch();
                    $row_count = $result['count'];
                } else {
                    $sql = "SELECT * FROM inh_prn_mst 
                    WHERE handle_fg IN ('0','1') {$sql_date1} {$sql_date2} {$sql_group} {$sql_key1} {$sql_key2} 
                    ORDER BY {$order1} {$power1} {$sql_order2};";
                    $pdo_stmt = $this->container->pdb->prepare($sql);
                    unset($value_array['limit']);
                    unset($value_array['offset']);
                    $pdo_stmt->execute($value_array);
                    $results = $pdo_stmt->fetchAll(PDO::FETCH_ASSOC);

                    $sql = "SELECT COUNT(*) FROM inh_prn_mst WHERE handle_fg IN ('0','1') {$sql_date1} {$sql_date2} {$sql_group} {$sql_key1} {$sql_key2}";
                    $pdo_stmt = $this->container->pdb->prepare($sql);
                    $pdo_stmt->execute($value_array);
                    $result = $pdo_stmt->fetch();
                    $row_count = $result['count'];
                }

                return [
                    'results' => $results,
                    'count' => $row_count,
                    'offset' => $offset
                ];

            } catch (PDOException $e) {
                die($e->getMessage());
            } catch (Exception $e) {
            }
        } // end of if !$error
        
        return [
            'error' => $error
        ];
    } // end of function data

    public function detailsPDF($request, $response, $args)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhr')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }

        $type = $args['type'];
        $arr = $this->data($request, $type);
        if (isset($arr['error']))
            return die('資料發生錯誤');

        $group = $request->getParam('group');
        $date1 = $request->getParam('date1');
        $date2 = $request->getParam('date2');
        $db_rows = (array)$arr['results'];

        // TCPDF 設定
        if ($type == 'small_pdf')
            $orientation = 'P';
        else if ($type == 'pdf')
            $orientation = 'L';

        require_once($this->container['tcpdf_directory'] . '/inh/detailsPDF.php');
    }

    
    public function printInh($request, $response)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhr')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }
        $serial_no = null !== $request->getParam('serial_no') ? trim($request->getParam('serial_no')) : '';
        if (empty($serial_no)) die('沒有設定系統辨識碼');
        
        $man_lot1 = null !== $request->getParam('man_lot1') ? trim($request->getParam('man_lot1')) : '';
        $inh_qty1 = null !== $request->getParam('inh_qty1') ? trim($request->getParam('inh_qty1')) : '';
        $man_lot2 = null !== $request->getParam('man_lot2') ? trim($request->getParam('man_lot2')) : '';
        $inh_qty2 = null !== $request->getParam('inh_qty2') ? trim($request->getParam('inh_qty2')) : '';
        $man_lot3 = null !== $request->getParam('man_lot3') ? trim($request->getParam('man_lot3')) : '';
        $inh_qty3 = null !== $request->getParam('inh_qty3') ? trim($request->getParam('inh_qty3')) : '';

        $name = $this->container->auth->getUser();
        $inh_dep = $this->container->auth->getGroup();

        $lot_no = null !== $request->getParam('lot_no') ? trim($request->getParam('lot_no')) : '';
        $part_no = null !== $request->getParam('part_no') ? trim($request->getParam('part_no')) : '';
        $part_name = null !== $request->getParam('part_name') ? trim($request->getParam('part_name')) : '';
        $unit = null !== $request->getParam('unit') ? trim($request->getParam('unit')) : '';
        $print_t = null !== $request->getParam('print_t') ? trim($request->getParam('print_t')) : 'one_by_one';

        if ($print_t !== 'one_by_one' && $print_t !== 'two_by_one') {
            $print_t = 'one_by_one';
        }
        
        $man_count = 0;
        $man_lot = '';
        $inh_qty = 0;
        $manCell = "製造批號\n";
        $qtyCell = "繳庫數\n";
        if (!empty($man_lot1)) {
            $man_count++;
            $man_lot = $man_lot1;
            $inh_qty += $inh_qty1;
            $manCell .= ($man_lot1."\n");
            $qtyCell .= ((int)$inh_qty1."\n");
        }
        if (!empty($man_lot2)) {
            $man_count++;
            $man_lot = $man_lot2;
            $inh_qty += $inh_qty2;
            $manCell .= ($man_lot2."\n");
            $qtyCell .= ((int)$inh_qty2."\n");
        }
        if (!empty($man_lot3)) {
            $man_count++;
            $man_lot = $man_lot3;
            $inh_qty += $inh_qty3;
            $manCell .= ($man_lot3);
            $qtyCell .= ((int)$inh_qty3);
        }

        if (isset($print_times) && (ctype_digit($print_times) || is_numeric($print_times))) {
            $print_times = ('#' . $print_times);
        } else {
            $print_times = '';
        }
        require_once($this->container['tcpdf_directory'] . '/inh/inhPDF.php');
    }

    public function searchView($request, $response)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhr')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }
        $this->container->view->render($response, 'inh/search.twig', [
            'menu_inh' => 'show'
        ]);
    }

    public function inhDelete($request, $response)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhd')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }
        $serial_no = null !== $request->getParam('serial_no') ? trim($request->getParam('serial_no')) : '';
        if (!empty($serial_no)) {
            
            $sql = "UPDATE inh_prn_mst SET handle_fg='2' WHERE serial_no = ?;";
            $pdo_stmt = $this->container->pdb->prepare($sql);
            $pdo_stmt->execute([$serial_no]);
            echo json_encode(array(
					'msg' => '已取消繳庫單,編號:' . $serial_no
				));
        } else {
            echo json_encode(array(
				'msg' => '資料錯誤！'
			));
        }
        exit();
    }

    public function inhRecover($request, $response)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhw')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }
        $serial_no = null !== $request->getParam('serial_no') ? trim($request->getParam('serial_no')) : '';
        if( !empty($serial_no)) {
            $sql = "UPDATE inh_prn_mst SET handle_fg='0' WHERE serial_no = ?;";
            $pdo_stmt = $this->container->pdb->prepare($sql);
            $pdo_stmt->execute([$serial_no]);
            echo json_encode(array(
					'msg' => '已復原繳庫單,編號:' . $serial_no
				));
        } else {
            echo json_encode(array(
				'msg' => '資料錯誤！'
			));
        }
        exit();
    }

    public function bstbJson($request, $response)
    {
        if (!$this->container->auth->checkAuth('inh', 'inhr')) {
            $this->container->flash->addMessage('error', ' # 無此權限 ');
            return $response->withRedirect($this->container->router->pathFor('home'));
        }
        $arr = $this->data($request, 'show');
        if (isset($arr['error']))
            $this->exitJson('資料發生錯誤' . var_dump($arr['error']));

        $data = [];
        foreach($arr['results'] as $row) {
            $bt = '';
            if($row['handle_fg'] == 0) {
                $status = '<a >未處理</a>';
                $bt = '<a class="remove btn btn-danger" href="javascript:void(0)" title="Remove">取消</a>';
            }
            else if($row['handle_fg'] == 1) {
                $status = '<a style="color:blue;">完成</a>';
            }
            else if($row['handle_fg'] == 2) {
                $status = '<a style="color:red;">已取消</a>';
                $bt = '<a class="recover btn btn-outline-secondary" href="javascript:void(0)" title="Recover">復原</a>';
            }

            $data[] = [
                "operate"   => $bt,
                "status"    => $status,
                "isu_serial" => $row['isu_serial'],
                "serial_no" => $row['serial_no'],
                "part_no"   => $row['part_no'],
                "part_name" => $row['part_name'],
                "line_name" => $row['line_name'], 
                "lot_no"    => $row['lot_no'], 
                "plan_qty"  => $row['plan_qty'], 
                "isu_qty"   => $row['isu_qty'], 
                "no_inh_qty" => $row['no_inh_qty'], 
                "man_lot1"  => $row['man_lot1'], 
                "inh_qty1"  => $row['inh_qty1'], 
                "man_lot2"  => $row['man_lot2'], 
                "inh_qty2"  => $row['inh_qty2'], 
                "man_lot3"  => $row['man_lot3'], 
                "inh_qty3"  => $row['inh_qty3'], 
                "inh_dep"   => $row['inh_dep'], 
                "plan_date" => $row['plan_date'], 
                "s_date"    => $row['s_date'],
                "e_date"    => $row['e_date']
                ];
        }

        return $response->withJson([
            'total' => $arr['count'],
            'rows' => $data
        ]);
    }

    public function qtReceiver($request, $response)
    {
        /*
            接收來自 Qt for android 的 https 資料: ( usedb, serial_no ) 使用的資料庫, 繳庫單編號
            到資料庫 192.168.5.2 抓取資料庫的 繳庫單主資料表(inh_prn_mst) 的資料進行列印
            2021.03.05 usedb 不使用
        */


        /*
        ini_set('display_errors', '1');
        error_reporting(E_ALL);

        foreach($_REQUEST as $key => $value)
        echo "Key: {$key}, Value: {$value}<br />" . PHP_EOL;
        exit();
        */

        $serial_no = null !== $request->getParam('serial_no') ? trim($request->getParam('serial_no')) : "";

        $int_options = array(
            "options" => array(
                "min_range" => 0,
                "max_range" => 9999999 
                )
            );
        $rt = filter_input(INPUT_POST, 'serial_no', FILTER_VALIDATE_INT, $int_options);
        if ($rt === false) exit("serial_no have a fatal error");

        $printer_list = array("1f_90", "2f_91");
        $printer = null !== $request->getParam('printer') ? trim($request->getParam('printer')) : "";
        if (!in_array($printer, $printer_list))
            exit("Fatal error no printer name.");
        $pdo = $this->container->pdb;

        try {

            $sql = "SELECT * FROM inh_prn_mst WHERE serial_no = ?";
            $pdos = $pdo->prepare($sql);
            $pdos->execute([ $serial_no ]);
            $result = $pdos->fetch(PDO::FETCH_ASSOC);
            if (!$result)
                exit("Not found inh data.");
            $user_no = trim($result["user_no"]);

            $sql_user = "SELECT * FROM user_code WHERE user_code = ?";
            $pdos = $pdo->prepare($sql_user);
            $pdos->execute([ $user_no ]);
            $result_user = $pdos->fetch(PDO::FETCH_ASSOC);
            if (!$result_user)
                exit("Not found user data.");
            $inh_dep = trim($result_user["group_code"]);
            $name = trim($result_user["user_name"]);
            
            $lot_no = trim($result["lot_no"]);
            $part_no = trim($result["part_no"]);
            $part_name = trim($result["part_name"]);
            $unit = trim($result["unit"]);
            $man_lot1 = trim($result["man_lot1"]);
            $inh_qty1 = trim($result["inh_qty1"]);
            $man_lot2 = trim($result["man_lot2"]);
            $inh_qty2 = trim($result["inh_qty2"]);
            $man_lot3 = trim($result["man_lot3"]);
            $inh_qty3 = trim($result["inh_qty3"]);

            $man_count = 0; // 輸入的生產批號數量
            $man_lot = '';
            $inh_qty = 0;   // 使用隱式轉換 非數字字串等於 0 ，boolean值 true=1 false=空值
            $manCell = "製造批號\n";
            $qtyCell = "繳庫數\n";
            if (!empty($man_lot1)) {
                $man_count++;
                $man_lot = $man_lot1;
                $inh_qty += $inh_qty1;
                $manCell .= ($man_lot1."\n");
                $qtyCell .= ((int)$inh_qty1."\n");
            }
            if (!empty($man_lot2)) {
                $man_count++;
                $man_lot = $man_lot2;
                $inh_qty += $inh_qty2;
                $manCell .= ($man_lot2."\n");
                $qtyCell .= ((int)$inh_qty2."\n");
            }
            if (!empty($man_lot3)) {
                $man_count++;
                $man_lot = $man_lot3;
                $inh_qty += $inh_qty3;
                $manCell .= ($man_lot3);
                $qtyCell .= ((int)$inh_qty3);
            }

            $serial_no = trim($result["serial_no"]);
            $print_times = (int)trim($result["print_times"]);
            if ($print_times > 1) {
                $print_times = ('#' . $print_times);
            } else {
                $print_times = '';
            }
             // print type
            $print_t = "one_by_one";
            //$print_t = "two_by_one";

            require_once($this->container['tcpdf_directory'] . '/inh/qt_receiver.php');
        } catch (PDOException $e) {
            return $e->getMessage();
        }
        
    }

} // end of class

