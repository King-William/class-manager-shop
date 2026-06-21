<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\ErrorCode;
use App\Service\UserService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Di\Annotation\Inject;
use App\Middleware\AuthMiddleware;

#[Controller(prefix: '/api/user')]
#[Middleware(middleware: AuthMiddleware::class)]
class UserController extends AbstractController
{
    #[Inject]
    protected UserService $userService;

    /**
     * 修改手机号.
     * POST /api/user/change-phone
     * Body: {"phone": "13800138000"}
     */
    #[RequestMapping(path: 'change-phone', methods: ['POST'])]
    public function changePhone(): \Psr\Http\Message\ResponseInterface
    {
        $phone = $this->request->input('post.phone', '');

        try {
            $this->userService->changePhone($phone);
        } catch (\App\Exception\BusinessException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        }

        return $this->success(['phone' => $phone], '修改成功');
    }

    /**
     * 修改昵称.
     * POST /api/user/change-nickname
     * Body: {"nickname": "新昵称"}
     */
    #[RequestMapping(path: 'change-nickname', methods: ['POST'])]
    public function changeNickname(): \Psr\Http\Message\ResponseInterface
    {
        $nickname = $this->request->input('post.nickname', '');

        try {
            $this->userService->changeNickname($nickname);
        } catch (\App\Exception\BusinessException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        }

        return $this->success(['nickname' => $nickname], '修改成功');
    }

    private function success(?array $data, string $msg = 'SUCCESS'): \Psr\Http\Message\ResponseInterface
    {
        return $this->response->json([
            'code' => ErrorCode::SUCCESS,
            'msg'  => $msg,
            'data' => $data,
        ]);
    }

    private function error(int $code, string $msg): \Psr\Http\Message\ResponseInterface
    {
        return $this->response->json([
            'code' => $code,
            'msg'  => $msg,
            'data' => null,
        ]);
    }
}
