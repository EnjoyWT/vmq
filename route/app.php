<?php

use think\facade\Route;
use app\index\controller\Index as IndexController;
use app\admin\controller\Index as AdminController;

Route::get('health', function () {
    return json(['status' => 'ok', 'service' => 'vmq']);
});

Route::any('login', [IndexController::class, 'login']);
Route::any('getMenu', [IndexController::class, 'getMenu']);
Route::any('enQrcode', [AdminController::class, 'enQrcode']);
Route::any('createOrder', [IndexController::class, 'createOrder']);


Route::any('getOrder', [IndexController::class, 'getOrder']);
Route::any('checkOrder', [IndexController::class, 'checkOrder']);
Route::any('getState', [IndexController::class, 'getState']);

Route::any('appHeart', [IndexController::class, 'appHeart']);
Route::any('appPush', [IndexController::class, 'appPush']);


Route::any('closeOrder', [IndexController::class, 'closeOrder']);
Route::any('closeEndOrder', [IndexController::class, 'closeEndOrder']);
Route::any('logout', [IndexController::class, 'logout']);
