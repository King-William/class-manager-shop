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

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

#[Constants]
class ErrorCode extends AbstractConstants
{
    /**
     * @Message("Server Error！")
     */
    public const SERVER_ERROR = 500;

    /**
     * @Message("身份验证未通过")
     */
    public const UNAUTHORIZED = 401;

    /**
     * @Message("访问路由不存在")
     */
    public const NOT_FOUND = 404;

    /**
     * @Message("禁止访问")
     */
    public const FORBIDDEN = 403;

    /**
     * @Message("手机号格式不正确")
     */
    public const PHONE_INVALID_FORMAT = 1002;

    /**
     * @Message("昵称不能为空")
     */
    public const NICKNAME_REQUIRED = 1003;

    /**
     * @Message("昵称长度不能超过64个字符")
     */
    public const NICKNAME_TOO_LONG = 1004;

    // ----- 通用 -----
    /**
     * @Message("SUCCESS")
     */
    public const SUCCESS = 0;

    /**
     * @Message("警告")
     */
    public const WARNING = 1;

    // ----- 班级相关 -----
    /**
     * @Message("班级名称不能为空")
     */
    public const CLASS_NAME_REQUIRED = 2001;

    /**
     * @Message("只有老师可以操作班级")
     */
    public const CLASS_ONLY_TEACHER = 2002;

    /**
     * @Message("班级不存在")
     */
    public const CLASS_NOT_FOUND = 2003;

    /**
     * @Message("学生已被分配到其他班级")
     */
    public const STUDENT_ALREADY_IN_CLASS = 2004;

    /**
     * @Message("学生不存在")
     */
    public const STUDENT_NOT_FOUND = 2005;

    /**
     * @Message("开始日期不能晚于结束日期")
     */
    public const DATE_RANGE_INVALID = 2006;

    /**
     * @Message("上课日格式错误")
     */
    public const CLASS_DAYS_INVALID = 2007;
}
