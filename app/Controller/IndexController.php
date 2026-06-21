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

namespace App\Controller;

use App\Constants\ErrorCode;
use App\Service\TokenService;

class IndexController extends AbstractController
{
    public function index(): \Psr\Http\Message\ResponseInterface
    {
        return $this->response->json([
            'code' => ErrorCode::SUCCESS,
            'msg'  => '欢迎使用 class-manager-shop API',
            'data' => [
                'version' => '1.0.0',
                'endpoints' => [
                    'auth' => '/api/auth/wechat-login',
                    'user' => '/api/user/change-phone',
                    'user' => '/api/user/change-nickname',
                    'class' => '/api/class/*',
                ],
            ],
        ]);
    }

    /**
     * 登出.
     * POST /api/auth/logout
     */
    public function logout(): \Psr\Http\Message\ResponseInterface
    {
        $token = $this->request->getHeaderLine('Authorization');
        $token = str_starts_with($token, 'Bearer ') ? substr($token, 7) : '';

        if ($token !== '') {
            make(TokenService::class)->logout($token);
        }

        return $this->response->json([
            'code' => ErrorCode::SUCCESS,
            'msg'  => '退出成功',
            'data' => null,
        ]);
    }
}
