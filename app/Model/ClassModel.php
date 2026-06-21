<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Model;

use Hyperf\DbConnection\Model\Model as BaseModel;

/**
 * @property int $id
 * @property string $name 班级名称
 * @property int $teacher_id 创建老师ID
 * @property string $start_date 总开始日期
 * @property string $end_date 总结束日期
 * @property string $class_days 每周上课日，逗号分隔如 "1,3,5"
 * @property string $class_start_time 每日开始时间
 * @property string $class_end_time 每日结束时间
 * @property string $created_at
 * @property string $updated_at
 */
class ClassModel extends BaseModel
{
    protected ?string $table = 'classes';

    protected array $attributes = [
        'id' => null,
        'name' => '',
        'teacher_id' => 0,
        'start_date' => '',
        'end_date' => '',
        'class_days' => '',
        'class_start_time' => '',
        'class_end_time' => '',
        'created_at' => null,
        'updated_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'teacher_id' => 'integer',
        ];
    }

    /**
     * 关联学生（通过 class_students 表）.
     */
    public function students(): \Hyperf\Database\Model\Relations\HasMany
    {
        return $this->hasMany(ClassStudent::class, 'class_id', 'id');
    }
}
