<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\User;
use Hyperf\Guzzle\ClientFactory;
use GuzzleException;
use Psr\Http\Client\ClientInterface;
use RuntimeException;
use Throwable;

use function Hyperf\Support\env;

class AuthService
{
    private ClientInterface $httpClient;

    public function __construct(ClientFactory $clientFactory)
    {
        // 设置合理的超时时间，防止微信 API 挂起协程
        $this->httpClient = $clientFactory->create([
            'timeout' => 10.0,
        ]);
    }

    /**
     * 小程序 code 登录完整流程：code -> openid -> 用户 -> token.
     *
     * @return array{token: string, user: array{uid: int, role: int, role_name: string}}
     */
    public function loginByMiniProgram(string $code): array
    {
        // 1. code 换 openid
        $session = $this->codeToSession($code);

        // 2. 查找或创建用户（使用 firstOrCreate 避免并发竞态）
        $user = User::query()->firstOrCreate(
            ['openid' => $session['openid']],
            ['role' => User::ROLE_STUDENT]
        );

        if ($user === null) {
            throw new RuntimeException('Failed to create or find user');
        }

        // 3. 生成 token
        $tokenService = make(TokenService::class);
        $token = $tokenService->generate([
            'uid' => $user->id,
            'openid' => $user->openid,
            'role' => $user->role,
            'role_name' => $user->role_name,
        ]);

        return [
            'token' => $token,
            'user' => [
                'uid' => $user->id,
                'role' => $user->role,
                'role_name' => $user->role_name,
            ],
        ];
    }

    /**
     * @return array{openid: string, session_key: string, unionid: string|null}
     */
    private function codeToSession(string $code): array
    {
        $appId = env('WECHAT_MINI_PROGRAM_APP_ID', '');
        $appSecret = env('WECHAT_MINI_PROGRAM_APP_SECRET', '');

        $url = 'https://api.weixin.qq.com/sns/jscode2session';
        $params = [
            'appid' => $appId,
            'secret' => $appSecret,
            'js_code' => $code,
            'grant_type' => 'authorization_code',
        ];

        try {
            $response = $this->httpClient->get($url, ['query' => $params]);
        } catch (GuzzleException $e) {
            // 网络异常时不暴露内部细节
            throw new BusinessException(ErrorCode::SERVER_ERROR);
        }

        $body = json_decode((string) $response->getBody(), true);

        if (isset($body['errcode'])) {
            // 微信 API 错误码映射为通用业务错误
            $errCode = (int) $body['errcode'];
            $messages = [
                40029 => 'code已失效，请重新登录',
                45011 => 'API调用频率超限，请稍后重试',
                -1 => '系统繁忙，请稍后重试',
            ];
            throw new BusinessException(
                $messages[$errCode] ?? ErrorCode::SERVER_ERROR,
                $messages[$errCode] ?? '微信登录失败'
            );
        }

        if (empty($body['openid'])) {
            throw new BusinessException(ErrorCode::SERVER_ERROR);
        }

        return [
            'openid' => $body['openid'],
            'session_key' => $body['session_key'],
            'unionid' => $body['unionid'] ?? null,
        ];
    }
}
