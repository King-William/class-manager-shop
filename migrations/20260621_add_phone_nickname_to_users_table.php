<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;
use Hyperf\Schema\Blueprint;

return class AddPhoneNicknameToUsersTable extends Migration
{
    public function up(): void
    {
        // 列已在 create_users_table 中定义，此处留空避免重复添加
    }

    public function down(): void
    {
        // 同上
    }
};
