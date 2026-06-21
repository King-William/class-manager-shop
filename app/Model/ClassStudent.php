<?php

declare(strict_types=1);

namespace App\Model;

/**
 * @property int $id
 * @property int $class_id 班级ID
 * @property int $student_id 学生ID
 * @property string $created_at
 */
class ClassStudent extends Model
{
    protected ?string $table = 'class_students';

    protected array $attributes = [
        'id' => null,
        'class_id' => 0,
        'student_id' => 0,
        'created_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'class_id' => 'integer',
            'student_id' => 'integer',
        ];
    }

    /**
     * 关联学生用户信息.
     */
    public function student(): \Hyperf\Database\Model\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id', 'id');
    }
}
