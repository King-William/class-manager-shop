-- ============================================================
-- Class Manager Shop - 数据库初始化 SQL
-- ============================================================
-- 项目: 培训班管理系统后端 (为微信小程序提供服务)
-- 框架: Hyperf 3.1 (PHP 8.0+)
-- 数据库: MySQL
-- 字符集: utf8
-- 排序规则: utf8_unicode_ci
-- 生成日期: 2026-06-22
-- ============================================================

-- 创建数据库（如不存在）
CREATE DATABASE IF NOT EXISTS `class_manager_shop` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `class_manager_shop`;

-- ============================================================
-- 表 1: users - 用户表
-- ============================================================
-- 存储所有用户（教师和学员）
-- 微信用户通过 openid 唯一标识
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID',
    `openid` VARCHAR(64) NOT NULL UNIQUE COMMENT '微信openid，用户唯一标识',
    `phone` VARCHAR(20) DEFAULT NULL COMMENT '手机号',
    `nickname` VARCHAR(64) DEFAULT NULL COMMENT '用户昵称',
    `gender` VARCHAR(2) DEFAULT NULL COMMENT '性别: m-男, f-女',
    `age` SMALLINT UNSIGNED DEFAULT NULL COMMENT '年龄',
    `class_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属班级ID，0表示未分配',
    `role` TINYINT NOT NULL DEFAULT 2 COMMENT '角色: 1-老师, 2-学生',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    INDEX `idx_role` (`role`),
    INDEX `idx_class_id` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ============================================================
-- 表 2: classes - 班级表
-- ============================================================
-- 存储培训班信息
-- class_days 字段存储逗号分隔的数字：1=周一, 2=周二, ..., 7=周日
DROP TABLE IF EXISTS `classes`;

CREATE TABLE `classes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID',
    `name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '班级名称',
    `teacher_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建老师ID，关联users.id',
    `start_date` DATE NOT NULL DEFAULT '' COMMENT '课程总开始日期',
    `end_date` DATE NOT NULL DEFAULT '' COMMENT '课程总结束日期',
    `class_days` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '每周上课日，逗号分隔: 1,3,5 表示周一、周三、周五',
    `class_start_time` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '每日开始时间，格式 HH:MM',
    `class_end_time` VARCHAR(10) NOT NULL DEFAULT '' COMMENT '每日结束时间，格式 HH:MM',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    INDEX `idx_teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='班级表';

-- ============================================================
-- 表 3: class_students - 班级学生关联表
-- ============================================================
-- 多对多关系：一个学生可以在多个班级（业务上限制每个学生在同一时间只在一个班级）
-- 联合唯一索引确保学生不会重复加入同一班级
DROP TABLE IF EXISTS `class_students`;

CREATE TABLE `class_students` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键ID',
    `class_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '班级ID，关联classes.id',
    `student_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '学生ID，关联users.id',
    `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '加入时间',
    UNIQUE INDEX `class_student_unique` (`class_id`, `student_id`),
    INDEX `idx_class_id` (`class_id`),
    INDEX `idx_student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='班级学生关联表';

-- ============================================================
-- 初始化数据（可选）
-- ============================================================

-- 创建一位默认管理员老师
-- openid 为示例值，实际由微信登录时自动生成
INSERT INTO `users` (`openid`, `phone`, `nickname`, `gender`, `role`)
VALUES ('demo_teacher_openid', '13800138000', '王老师', 'm', 1);

-- ============================================================
-- 字典说明
-- ============================================================
-- users.role:
--   1 = 老师 (Teacher)
--   2 = 学生 (Student)
--
-- users.gender:
--   m = 男 (Male)
--   f = 女 (Female)
--
-- classes.class_days:
--   1 = 周一, 2 = 周二, 3 = 周三, 4 = 周四, 5 = 周五, 6 = 周六, 7 = 周日
--   存储格式: 逗号分隔，如 "1,3,5" 表示周一、周三、周五上课
--
-- class_students.created_at:
--   学生加入班级的时间

-- ============================================================
-- 表结构关系图
-- ============================================================
--
--   users                    classes                 class_students
-- +------------+         +-------------+        +------------------+
-- | id (PK)    |<------+ | id (PK)     |      --| id (PK)          |
-- | openid     |  1:N    | teacher_id |--+---->| class_id (FK)    |
-- | phone      |         | name        |  |     | student_id (FK)  |
-- | nickname   |         | start_date  |  |     | created_at       |
-- | gender     |         | end_date    |  |     +------------------+
-- | age        |         | class_days  |   \   | 唯一: class_id+student_id
-- | class_id   |         | start_time  |    \
-- | role       |         | end_time    |     |
-- | created_at |         +-------------+     |
-- | updated_at |          1:N               /
-- +------------+         +------------------+
--        |                   |
--        |  N:1              |
--        +-------------------+
--
-- 说明：
--   1. users.class_id -> classes.id  (学生所属班级，软关联)
--   2. classes.teacher_id -> users.id (创建班级的老师)
--   3. class_students.class_id -> classes.id (班级学生关联)
--   4. class_students.student_id -> users.id (关联学生用户)
