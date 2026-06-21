<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\ClassModel;
use App\Model\ClassStudent;
use App\Model\User;
use Hyperf\Context\Context;
use Hyperf\Database\Model\Collection;
use Hyperf\DbConnection\Db;
use RuntimeException;
use Throwable;

class ClassService
{
    /**
     * 创建班级并关联学生.
     *
     * @param int[] $studentIds
     */
    public function createClass(array $params, int $teacherId): array
    {
        $validated = $this->validateClassParams($params);

        // 检查日期范围
        if (strtotime($validated['end_date']) < strtotime($validated['start_date'])) {
            throw new BusinessException(ErrorCode::DATE_RANGE_INVALID);
        }

        // 检查学生是否已被分配到其他班级
        $this->checkStudentsAvailability($validated['student_ids'] ?? []);

        try {
            return Db::transaction(function () use ($validated, $teacherId) {
                $class = new ClassModel();
                $class->name = $validated['name'];
                $class->teacher_id = $teacherId;
                $class->start_date = $validated['start_date'];
                $class->end_date = $validated['end_date'];
                $class->class_days = implode(',', array_unique($validated['class_days']));
                $class->class_start_time = $validated['class_start_time'];
                $class->class_end_time = $validated['class_end_time'];
                $class->save();

                $this->assignStudentsToClass($class->id, $validated['student_ids'] ?? []);

                return ['id' => (int) $class->id];
            });
        } catch (BusinessException) {
            throw;
        } catch (\PDOException $e) {
            // 捕获唯一索引冲突等数据库异常
            throw new BusinessException(ErrorCode::SERVER_ERROR);
        } catch (Throwable $e) {
            throw new BusinessException(ErrorCode::SERVER_ERROR);
        }
    }

    /**
     * 编辑班级.
     */
    public function updateClass(array $params, int $teacherId): void
    {
        $classId = $params['id'] ?? 0;
        if ($classId <= 0) {
            throw new BusinessException(ErrorCode::CLASS_NOT_FOUND);
        }

        $class = ClassModel::query()->find($classId);
        if ($class === null) {
            throw new BusinessException(ErrorCode::CLASS_NOT_FOUND);
        }

        // 只能编辑自己创建的班级
        if ((int) $class->teacher_id !== $teacherId) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED);
        }

        if (isset($params['name'])) {
            $class->name = trim($params['name']);
            if ($class->name === '') {
                throw new BusinessException(ErrorCode::CLASS_NAME_REQUIRED);
            }
        }
        if (isset($params['start_date']) && isset($params['end_date'])) {
            if (strtotime($params['end_date']) < strtotime($params['start_date'])) {
                throw new BusinessException(ErrorCode::DATE_RANGE_INVALID);
            }
            $class->start_date = $params['start_date'];
            $class->end_date = $params['end_date'];
        }
        if (isset($params['class_days'])) {
            $class->class_days = implode(',', array_unique($params['class_days']));
        }
        if (isset($params['class_start_time'])) {
            $class->class_start_time = $params['class_start_time'];
        }
        if (isset($params['class_end_time'])) {
            $class->class_end_time = $params['class_end_time'];
        }

        // 如果修改了时间范围，校验 start < end
        if (($class->class_start_time !== '' || isset($params['class_start_time']))
            && ($class->class_end_time !== '' || isset($params['class_end_time']))) {
            if ($class->class_start_time >= $class->class_end_time) {
                throw new BusinessException(ErrorCode::DATE_RANGE_INVALID);
            }
        }

        $class->save();
    }

    /**
     * 获取班级列表（按老师）.
     *
     * @return array{list: array[], total: int}
     */
    public function getClassList(int $teacherId, int $page = 1, int $pageSize = 10): array
    {
        $query = ClassModel::query()->where('teacher_id', $teacherId);
        $total = (int) $query->count();
        $offset = ($page - 1) * $pageSize;

        // 使用子查询批量统计学生数，避免 N+1
        $classes = $query->selectRaw('*, (SELECT COUNT(*) FROM class_students WHERE class_students.class_id = classes.id) AS student_count')
            ->orderBy('id', 'desc')
            ->skip($offset)
            ->take($pageSize)
            ->get();

        $list = [];
        foreach ($classes as $class) {
            $list[] = [
                'id' => $class->id,
                'name' => $class->name,
                'start_date' => $class->start_date,
                'end_date' => $class->end_date,
                'class_days' => $this->formatClassDays($class->class_days),
                'class_start_time' => $class->class_start_time,
                'class_end_time' => $class->class_end_time,
                'student_count' => (int) ($class->student_count ?? 0),
            ];
        }

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 获取班级详情（含学生列表）.
     */
    public function getClassDetail(int $classId): array
    {
        $class = ClassModel::query()->find($classId);
        if ($class === null) {
            throw new BusinessException(ErrorCode::CLASS_NOT_FOUND);
        }

        // 获取学生列表
        $students = ClassStudent::query()
            ->where('class_id', $classId)
            ->with('student')
            ->get();

        $studentList = [];
        foreach ($students as $cs) {
            $student = $cs->student;
            if ($student !== null) {
                $studentList[] = [
                    'id' => $student->id,
                    'name' => $student->nickname,
                    'phone' => $student->phone,
                    'age' => $student->age,
                    'gender' => $student->gender,
                ];
            }
        }

        return [
            'id' => $class->id,
            'name' => $class->name,
            'teacher_id' => (int) $class->teacher_id,
            'start_date' => $class->start_date,
            'end_date' => $class->end_date,
            'class_days' => $this->formatClassDays($class->class_days),
            'class_start_time' => $class->class_start_time,
            'class_end_time' => $class->class_end_time,
            'students' => $studentList,
        ];
    }

    /**
     * 添加学生到班级.
     *
     * @param int[] $studentIds
     */
    public function addStudents(int $classId, array $studentIds): void
    {
        $class = ClassModel::query()->find($classId);
        if ($class === null) {
            throw new BusinessException(ErrorCode::CLASS_NOT_FOUND);
        }

        $this->checkStudentsAvailability($studentIds, $classId);

        try {
            Db::transaction(function () use ($classId, $studentIds) {
                foreach ($studentIds as $studentId) {
                    // 使用 insert 避免重复插入，利用唯一索引去重
                    ClassStudent::query()->insertOrIgnore([
                        'class_id' => $classId,
                        'student_id' => $studentId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);

                    // 同步更新 users 表的 class_id
                    User::query()->where('id', $studentId)->update(['class_id' => $classId]);
                }
            });
        } catch (\PDOException $e) {
            throw new BusinessException(ErrorCode::SERVER_ERROR);
        }
    }

    /**
     * 移除学生.
     *
     * @param int[] $studentIds
     */
    public function removeStudents(int $classId, array $studentIds): void
    {
        try {
            Db::transaction(function () use ($classId, $studentIds) {
                // 只移除该班级下的学生
                ClassStudent::query()
                    ->where('class_id', $classId)
                    ->whereIn('student_id', $studentIds)
                    ->delete();

                // 只清除属于该班级的学生的 class_id
                User::query()
                    ->whereIn('id', $studentIds)
                    ->where('class_id', $classId)
                    ->update(['class_id' => 0]);
            });
        } catch (Throwable $e) {
            throw new BusinessException(ErrorCode::SERVER_ERROR);
        }
    }

    /**
     * 获取所有未被分配到班级的学生（可用于新建班级时选择）.
     *
     * @return array<int, array{name: string, phone: string, age: int|null, gender: string|null}>
     */
    public function getUnassignedStudents(): array
    {
        $students = User::query()
            ->where('role', User::ROLE_STUDENT)
            ->where('class_id', 0)
            ->get(['id', 'nickname', 'phone', 'age', 'gender']);

        $list = [];
        foreach ($students as $student) {
            $list[] = [
                'id' => $student->id,
                'name' => $student->nickname,
                'phone' => $student->phone,
                'age' => $student->age,
                'gender' => $student->gender,
            ];
        }

        return $list;
    }

    /**
     * 获取所有学生（含已分配班级的），用于班级内选择学生.
     *
     * @return array<int, array{id: int, name: string, phone: string, age: int|null, gender: string|null, class_id: int}>
     */
    public function getAllStudents(): array
    {
        $students = User::query()
            ->where('role', User::ROLE_STUDENT)
            ->get(['id', 'nickname', 'phone', 'age', 'gender', 'class_id']);

        $list = [];
        foreach ($students as $student) {
            $list[] = [
                'id' => $student->id,
                'name' => $student->nickname,
                'phone' => $student->phone,
                'age' => $student->age,
                'gender' => $student->gender,
                'class_id' => (int) $student->class_id,
            ];
        }

        return $list;
    }

    /**
     * 校验班级参数.
     *
     * @return array{name: string, start_date: string, end_date: string, class_days: int[], class_start_time: string, class_end_time: string, student_ids?: int[]}
     */
    private function validateClassParams(array $params): array
    {
        $name = trim($params['name'] ?? '');
        if ($name === '') {
            throw new BusinessException(ErrorCode::CLASS_NAME_REQUIRED);
        }

        if (empty($params['start_date']) || empty($params['end_date'])) {
            throw new BusinessException(ErrorCode::DATE_RANGE_INVALID);
        }

        $classDays = $params['class_days'] ?? [];
        if (!is_array($classDays) || empty($classDays)) {
            throw new BusinessException(ErrorCode::CLASS_DAYS_INVALID);
        }

        $validDays = [];
        foreach ($classDays as $day) {
            $dayInt = (int) $day;
            if (!in_array($dayInt, [1, 2, 3, 4, 5, 6, 7], true)) {
                throw new BusinessException(ErrorCode::CLASS_DAYS_INVALID);
            }
            $validDays[] = $dayInt;
        }

        $startTime = $params['class_start_time'] ?? '';
        $endTime = $params['class_end_time'] ?? '';
        if ($startTime === '' || $endTime === '') {
            throw new BusinessException(ErrorCode::DATE_RANGE_INVALID);
        }

        if ($startTime >= $endTime) {
            throw new BusinessException(ErrorCode::DATE_RANGE_INVALID);
        }

        return [
            'name' => $name,
            'start_date' => $params['start_date'],
            'end_date' => $params['end_date'],
            'class_days' => $validDays,
            'class_start_time' => $startTime,
            'class_end_time' => $endTime,
            'student_ids' => $params['student_ids'] ?? [],
        ];
    }

    /**
     * 检查学生是否可用（未被分配到其他班级）.
     * 优先查询 class_students 表作为权威来源，fallback 到 users.class_id.
     *
     * @param int[] $studentIds
     */
    private function checkStudentsAvailability(array $studentIds, ?int $exceptClassId = null): void
    {
        if (empty($studentIds)) {
            return;
        }

        // 从 class_students 表中查询真实分配关系
        $enrolledStudentIds = ClassStudent::query()
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id')
            ->toArray();

        foreach ($enrolledStudentIds as $studentId) {
            // 排除当前正在更新的班级
            if ($exceptClassId !== null) {
                $existing = ClassStudent::query()
                    ->where('student_id', $studentId)
                    ->where('class_id', '!=', $exceptClassId)
                    ->exists();
                if ($existing) {
                    throw new BusinessException(ErrorCode::STUDENT_ALREADY_IN_CLASS);
                }
            } else {
                throw new BusinessException(ErrorCode::STUDENT_ALREADY_IN_CLASS);
            }
        }
    }

    /**
     * 将学生分配到班级.
     *
     * @param int[] $studentIds
     */
    private function assignStudentsToClass(int $classId, array $studentIds): void
    {
        if (empty($studentIds)) {
            return;
        }

        foreach ($studentIds as $studentId) {
            ClassStudent::query()->create([
                'class_id' => $classId,
                'student_id' => $studentId,
            ]);

            User::query()->where('id', $studentId)->update(['class_id' => $classId]);
        }
    }

    /**
     * 格式化上课日为可读字符串.
     */
    private function formatClassDays(string $classDays): string
    {
        if ($classDays === '') {
            return '';
        }

        $days = array_map('intval', explode(',', $classDays));
        $dayMap = [
            1 => '周一',
            2 => '周二',
            3 => '周三',
            4 => '周四',
            5 => '周五',
            6 => '周六',
            7 => '周日',
        ];

        $names = [];
        foreach ($days as $day) {
            $names[] = $dayMap[$day] ?? '';
        }

        return implode('、', array_filter($names));
    }
}
