<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;
use Hyperf\Schema\Blueprint;

return class CreateUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('openid', 64)->unique()->comment('微信openid');
            $table->string('phone', 20)->nullable()->after('openid')->comment('手机号');
            $table->string('nickname', 64)->nullable()->after('phone')->comment('用户昵称');
            $table->string('gender', 2)->nullable()->after('nickname')->comment('性别:m/f');
            $table->smallInteger('age')->nullable()->after('gender')->comment('年龄');
            $table->integer('class_id')->default(0)->after('age')->comment('所属班级ID');
            $table->tinyInteger('role')->default(2)->comment('角色:1-老师,2-学生');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
