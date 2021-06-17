<?php

use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\OfficialDocController;
use App\Controllers\MISController;
use App\Controllers\InhController;

use App\Middleware\RedirectIfUnauthenticated;


$app->group('', function() {

    $this->get('/', HomeController::class . ':index')->setName('home');
    $this->get('/group/users', HomeController::class . ':getGroupUsers')->setName('group.users');

    $this->get('/logout', AuthController::class . ':logout')->setName('logout');
    $this->get('/reset-password', AuthController::class . ':resetPasswordView')->setName('reset-password');
    $this->post('/reset-password', AuthController::class . ':resetPassword');

    $this->group('/mis', function() {
        $this->get('/register', MISController::class . ':registerView')->setName('register');
        $this->post('/register', MISController::class . ':register');
        $this->get('/set-password', MISController::class . ':setPasswordView')->setName('set-password');
        $this->post('/set-password', MISController::class . ':setPassword');
        $this->get('/modify-user-profile', MISController::class . ':modifyUserProfileView')->setName('modify-user-profile');
        $this->post('/modify-user-profile', MISController::class . ':modifyUserProfile');
        $this->get('/user-profile-json', MISController::class . ':userProfileJson')->setName('user-profile-json');
    });

    $this->group('/inh', function() {
        $this->get('/show', InhController::class . ':showView')->setName('inh.show');
        $this->get('/details-pdf/{type}', InhController::class . ':detailsPDF')->setName('inh.details-pdf');
        $this->get('/details/{type}', InhController::class . ':details')->setName('inh.details');
        $this->get('/details-xls', InhController::class . ':detailsXLS')->setName('inh.details-xls');
        $this->get('/details-xlsx', InhController::class . ':detailsXLSX')->setName('inh.details-xlsx');
        $this->get('/work', InhController::class . ':inhView')->setName('inh');
        $this->get('/work/json', InhController::class . ':inhWorkJson')->setName('inh.work.json');
        $this->post('/input', InhController::class . ':input')->setName('inh.input');
        $this->post('/get-temp', InhController::class . ':getTemp')->setName('inh.get-temp');
        $this->post('/del-temp', InhController::class . ':delTemp')->setName('inh.del-temp');
        $this->get('/print', InhController::class . ':printInh')->setName('inh.print');
        $this->get('/search', InhController::class . ':searchView')->setName('inh.search');
        $this->post('/delete', InhController::class . ':inhDelete')->setName('inh.delete');
        $this->post('/recover', InhController::class . ':inhRecover')->setName('inh.recover');
        $this->get('/bstb/json', InhController::class . ':bstbJson')->setName('inh.bstb.json');
    });
    
    
    $this->group('/official-doc', function() {
        $this->get('/launch', OfficialDocController::class . ':launchView')->setName('official-doc.launch');
        $this->post('/launch', OfficialDocController::class . ':launch');
        $this->get('/data/json', OfficialDocController::class . ':dataJson')->setName('official-doc.data.json');
        $this->get('/detail/{data_id}', OfficialDocController::class . ':detailView')->setName('official-doc.detail');
        $this->get('/check-doc/{data_id}', OfficialDocController::class . ':checkView')->setName('official-doc.check');
        $this->post('/check-doc', OfficialDocController::class . ':checkDoc')->setName('official-doc.check-doc');
        $this->get('download/{file_name}', OfficialDocController::class . ':download')->setName('official.download');
    });

    
})->add(new RedirectIfUnauthenticated($container));

$app->get('/login', AuthController::class . ':loginView')->setName('login');
$app->post('/login', AuthController::class . ':login');

$app->post('/tcpdf/qt_receiver.php', InhController::class . ':qtReceiver' )->setName('qt_receiver');

// 回應錯誤狀態
//$app->get('/', function($request, $response) { return $response->withStatus(404); });


