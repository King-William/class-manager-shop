<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;
use Hyperf\Schema\Blueprint;

return class CreateClassesTable extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100)->comment('班级名称');
            $table->integer('teacher_id')->default(0)->comment('创建老师ID');
            $table->date('start_date')->comment('总开始日期');
            $table->date('end_date')->comment('总结束日期');
            $table->string('class_days', 50)->default('')->comment('每周上课日 逗号分隔 1=周一');
            $table->string('class_start_time', 10)->default('')->comment('每日开始时间');
            $table->string('class_end_time', 10)->default('')->comment('每日结束时间');
            $table->timestamps();
            $table->index('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
