<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Constants\ErrorCode;
use App\Service\TokenService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Middleware\AbstractMiddleware;
use Hyperf\HttpServer\Router\Dispatched;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TokenDecodeMiddleware extends AbstractMiddleware
{
    protected array $skipRoutes = [
        '/api/auth/wechat-login',
        '/favicon.ico',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri()->getPath();
        if (in_array($uri, $this->skipRoutes, true)) {
            return $handler->handle($request);
        }

        $token = $this->extractToken($request);
        if ($token !== null) {
            // 校验 token 格式（UUID v4）
            if (!$this->isValidTokenFormat($token)) {
                return $this->response->json([
                    'code' => ErrorCode::UNAUTHORIZED,
                    'msg'  => ErrorCode::getMessage(ErrorCode::UNAUTHORIZED),
                    'data' => null,
                ])->withStatus(200);
            }

            $payload = make(TokenService::class)->verify($token);
            if ($payload !== null && isset($payload['uid'])) {
                Context::set('MemberId', (int) $payload['uid']);
            }
        }

        return $handler->handle($request);
    }

    /**
     * 从 Authorization header 中提取 Bearer token.
     */
    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if (str_starts_with($header, 'Bearer ')) {
            $token = substr($header, 7);
            // 限制最大长度，防止超长 token 攻击
            if (strlen($token) > 255) {
                return null;
            }
            return $token;
        }

        return null;
    }

    /**
     * 校验 token 是否为合法的 UUID v4 格式.
     */
    private function isValidTokenFormat(string $token): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $token
        ) === 1;
    }
}
