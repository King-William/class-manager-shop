<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\ErrorCode;
use App\Service\AuthService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

#[Controller(prefix: '/api/auth')]
#[Middleware(middleware: App\Middleware\AuthMiddleware::class)]
class AuthController extends AbstractController
{
    #[Inject]
    protected RequestInterface $request;

    #[Inject]
    protected ResponseInterface $response;

    #[Inject]
    protected AuthService $authService;

    /**
     * 微信小程序登录.
     * POST /api/auth/wechat-login
     * Body: {"code": "wx_login_code"}
     */
    #[RequestMapping(path: 'wechat-login', methods: ['POST'])]
    public function wechatLogin(): \Psr\Http\Message\ResponseInterface
    {
        $code = $this->request->input('post.code', '');

        if ($code === '') {
            return $this->response->json([
                'code' => ErrorCode::UNAUTHORIZED,
                'msg'  => '登录凭证不能为空',
                'data' => null,
            ])->withStatus(200);
        }

        try {
            $result = $this->authService->loginByMiniProgram($code);
        } catch (\Exception $e) {
            return $this->response->json([
                'code' => ErrorCode::SERVER_ERROR,
                'msg'  => '微信登录失败，请稍后重试',
                'data' => null,
            ])->withStatus(200);
        }

        return $this->response->json([
            'code' => ErrorCode::SUCCESS,
            'msg'  => '登录成功',
            'data' => $result,
        ]);
    }
}
