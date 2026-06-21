<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Schema;
use Hyperf\Schema\Blueprint;

return class CreateClassStudentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('class_students', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('class_id')->comment('班级ID');
            $table->integer('student_id')->comment('学生ID(users.id)');
            $table->timestamp('created_at')->nullable();
            $table->unique(['class_id', 'student_id'], 'class_student_unique');
            $table->index('class_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_students');
    }
};
