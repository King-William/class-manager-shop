<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\User;
use Hyperf\Context\Context;

class UserService
{
    /**
     * 修改手机号.
     */
    public function changePhone(string $phone): void
    {
        $phone = trim($phone);
        if ($phone === '') {
            throw new BusinessException(ErrorCode::PHONE_INVALID_FORMAT);
        }
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            throw new BusinessException(ErrorCode::PHONE_INVALID_FORMAT);
        }

        $userId = $this->currentUserId();
        $user = User::query()->find($userId);
        if ($user === null) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED);
        }

        $user->phone = $phone;
        $user->save();
    }

    /**
     * 修改昵称.
     */
    public function changeNickname(string $rawNickname): void
    {
        $nickname = trim($rawNickname);

        if ($nickname === '') {
            throw new BusinessException(ErrorCode::NICKNAME_REQUIRED);
        }

        if (mb_strlen($nickname) > 64) {
            throw new BusinessException(ErrorCode::NICKNAME_TOO_LONG);
        }

        $userId = $this->currentUserId();
        $user = User::query()->find($userId);
        if ($user === null) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED);
        }

        $user->nickname = $nickname;
        $user->save();
    }

    private function currentUserId(): int
    {
        $id = Context::get('MemberId', 0);
        return (int) $id;
    }
}
