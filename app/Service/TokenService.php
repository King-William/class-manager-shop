<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;

class TokenService
{
    private const TOKEN_PREFIX = 'class:token:';
    private const TOKEN_TTL = 86400 * 7; // 7天
    private const MAX_LIFETIME = 86400 * 7; // 最大存活时间（与TTL一致）

    private RedisFactory $redis;

    public function __construct(ContainerInterface $container)
    {
        $this->redis = $container->get(RedisFactory::class);
    }

    /**
     * 生成 token，存入 redis，返回 token 字符串.
     *
     * @param array{uid: int, openid: string, role: int, role_name: string} $userInfo
     */
    public function generate(array $userInfo): string
    {
        $token = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $key = self::TOKEN_PREFIX . $token;

        $payload = [
            'uid' => $userInfo['uid'],
            'role' => $userInfo['role'],
            'issued_at' => time(),
        ];

        // 使用 SET + EXPIRE 原子操作
        $this->redis->set($key, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->redis->expire($key, self::TOKEN_TTL);

        return $token;
    }

    /**
     * 验证 token，返回 payload；无效返回 null.
     * 包含 issued_at 绝对过期检查。
     *
     * @return array<string, mixed>|null
     */
    public function verify(string $token): ?array
    {
        $key = self::TOKEN_PREFIX . $token;
        $data = $this->redis->get($key);

        if ($data === false || $data === null) {
            return null;
        }

        $payload = json_decode($data, true);
        if ($payload === null || !isset($payload['uid'])) {
            return null;
        }

        // 检查绝对过期时间（issued_at + MAX_LIFETIME）
        if (isset($payload['issued_at'])) {
            $elapsed = time() - $payload['issued_at'];
            if ($elapsed > self::MAX_LIFETIME) {
                // 超过最大存活期，删除 token
                $this->redis->del($key);
                return null;
            }
        }

        // 原子化续期：仅当 token 值未变时才延长 TTL
        $lua = <<<'LUA'
if redis.call("GET", KEYS[1]) == ARGV[1] then
    redis.call("EXPIRE", KEYS[1], tonumber(ARGV[2]))
    return 1
else
    return 0
end
LUA;
        $this->redis->eval($lua, [$key, $data, self::TOKEN_TTL], 1);

        return $payload;
    }

    /**
     * 根据 token 获取 uid.
     */
    public function getUserId(string $token): ?int
    {
        $info = $this->verify($token);
        return $info['uid'] ?? null;
    }

    /**
     * 登出，删除 token.
     */
    public function logout(string $token): void
    {
        $this->redis->del(self::TOKEN_PREFIX . $token);
    }
}
