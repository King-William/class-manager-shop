<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

use App\Controller\IndexController;
use Hyperf\HttpServer\Router\Router;

// Favicon
Router::get('/favicon.ico', function () {
    return '';
});

// 首页
Router::get('/', [IndexController::class, 'index']);

// 登出
Router::post('/api/auth/logout', [IndexController::class, 'logout']);
