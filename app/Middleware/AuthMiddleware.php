<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Constants\ErrorCode;
use App\Model\User;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Middleware\AbstractMiddleware;
use Hyperf\HttpServer\Router\Dispatched;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware extends AbstractMiddleware
{
    /**
     * 仅老师可访问的路由前缀.
     */
    protected array $teacherOnlyPrefixes = [
        '/api/class',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 路由未匹配时返回 404
        $dispatched = $request->getAttribute(Dispatched::class);
        if (empty($dispatched) || empty($dispatched->handler)) {
            return $this->jsonResponse([
                'code' => ErrorCode::NOT_FOUND,
                'msg'  => ErrorCode::getMessage(ErrorCode::NOT_FOUND),
                'data' => null,
            ])->withStatus(404);
        }

        // 检查用户是否已登录
        $memberId = Context::get('MemberId', 0);
        if (empty($memberId)) {
            return $this->jsonResponse([
                'code' => ErrorCode::UNAUTHORIZED,
                'msg'  => ErrorCode::getMessage(ErrorCode::UNAUTHORIZED),
                'data' => null,
            ])->withStatus(401);
        }

        // 老师专属接口校验
        $uri = $request->getUri()->getPath();
        foreach ($this->teacherOnlyPrefixes as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                $user = User::query()->find($memberId);
                if ($user === null || $user->role !== User::ROLE_TEACHER) {
                    return $this->jsonResponse([
                        'code' => ErrorCode::CLASS_ONLY_TEACHER,
                        'msg'  => ErrorCode::getMessage(ErrorCode::CLASS_ONLY_TEACHER),
                        'data' => null,
                    ])->withStatus(403);
                }
                break;
            }
        }

        return $handler->handle($request);
    }

    /**
     * 辅助方法：构造 JSON 响应.
     */
    private function jsonResponse(array $data): ResponseInterface
    {
        return $this->response->json($data);
    }
}
