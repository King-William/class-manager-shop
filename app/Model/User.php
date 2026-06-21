<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Relations\HasOne;

/**
 * @property int $id
 * @property string|null $openid 微信openid
 * @property string|null $phone 手机号
 * @property string|null $nickname 用户昵称
 * @property string|null $gender 性别:m/f
 * @property int|null $age 年龄
 * @property int $class_id 所属班级ID
 * @property int $role 角色:1-老师,2-学生
 * @property string $created_at
 * @property string $updated_at
 */
class User extends Model
{
    public const ROLE_TEACHER = 1;
    public const ROLE_STUDENT = 2;

    public const ROLE_MAP = [
        self::ROLE_TEACHER => 'teacher',
        self::ROLE_STUDENT => 'student',
    ];

    /**
     * The table associated with the model.
     */
    protected ?string $table = 'users';

    protected array $attributes = [
        'id' => null,
        'openid' => null,
        'phone' => null,
        'nickname' => null,
        'gender' => null,
        'age' => null,
        'class_id' => 0,
        'role' => self::ROLE_STUDENT,
        'created_at' => null,
        'updated_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'role' => 'integer',
        ];
    }

    /**
     * 根据 openid 查找或创建用户（自动注册）
     */
    public static function findByOpenidOrCreate(string $openid): self
    {
        $user = self::query()->where('openid', $openid)->first();
        if ($user !== null) {
            return $user;
        }

        $user = new self();
        $user->openid = $openid;
        $user->role = self::ROLE_STUDENT; // 默认学生
        $user->save();

        return $user;
    }

    /**
     * 根据 openid 查找用户
     */
    public static function findByOpenid(string $openid): ?self
    {
        return self::query()->where('openid', $openid)->first();
    }

    /**
     * 获取角色名称
     */
    public function getRoleNameAttribute(): string
    {
        return self::ROLE_MAP[$this->role] ?? 'unknown';
    }
}
