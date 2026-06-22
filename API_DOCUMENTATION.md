# Class Manager Shop - API 接口文档

> 培训班管理系统后端 API，为微信小程序提供服务
> 
> 基础地址: `http://localhost:9501` | 版本: `1.0.0`
>
> 服务器: Hyperf 3.1 (PHP 8.0+) on Swoole | 监听端口: `9501`

---

## 目录

1. [通用规范](#1-通用规范)
2. [认证模块 (Auth)](#2-认证模块-auth)
3. [用户模块 (User)](#3-用户模块-user)
4. [班级模块 (Class)](#4-班级模块-class)
5. [错误码对照表](#5-错误码对照表)
6. [中间件说明](#6-中间件说明)

---

## 1. 通用规范

### 1.1 响应格式

所有接口统一返回以下 JSON 格式：

```json
{
  "code": 0,
  "msg": "SUCCESS",
  "data": {}
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| code | int | 状态码，`0` 表示成功 |
| msg | string | 提示信息 |
| data | mixed | 业务数据，失败时为 `null` |

### 1.2 认证方式

- 除登录接口外，所有接口需在请求头携带 Bearer Token：
  ```
  Authorization: Bearer {token}
  ```
- Token 为 UUID v4 格式，存储在 Redis 中，有效期 7 天（滑动续期）
- 登录接口 `POST /api/auth/wechat-login` 无需 Token

### 1.3 角色说明

| 角色 | 值 | 说明 |
|------|-----|------|
| Teacher | 1 | 教师，可管理班级 |
| Student | 2 | 学生，仅可修改个人信息 |

### 1.4 分页参数

分页接口统一使用以下参数：

| 参数 | 类型 | 默认值 | 最大值 | 说明 |
|------|------|--------|--------|------|
| page | int | 1 | - | 页码（从 1 开始） |
| page_size | int | 10 | 100 | 每页条数 |

---

## 2. 认证模块 (Auth)

### 2.1 微信小程序登录

> **POST** `/api/auth/wechat-login`

微信小程序通过 `code` 换取用户信息和 Token。此接口无需认证。

**请求体：**

```json
{
  "code": "wx_login_code_string"
}
```

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| code | string | 是 | 微信小程序 `wx.login()` 返回的登录凭证 |

**响应示例（成功）：**

```json
{
  "code": 0,
  "msg": "登录成功",
  "data": {
    "token": "550e8400-e29b-41d4-a716-446655440000",
    "user": {
      "uid": 1,
      "role": 2,
      "role_name": "student"
    }
  }
}
```

**业务流程：**
1. 前端调用 `wx.login()` 获取 `code`
2. 将 `code` 发送至本接口
3. 后端调用微信 `sns/jscode2session` 接口，用 `code` 换取 `openid`
4. 根据 `openid` 查询用户，不存在则自动创建（默认角色为学生）
5. 生成 UUID Token 存入 Redis（7 天有效期），返回 Token 和用户信息

**错误响应：**

```json
{
  "code": 500,
  "msg": "微信登录失败，请稍后重试",
  "data": null
}
```

---

### 2.2 退出登录

> **POST** `/api/auth/logout`

删除当前 Token，使其失效。

**请求头：**

```
Authorization: Bearer {token}
```

**响应示例：**

```json
{
  "code": 0,
  "msg": "退出成功",
  "data": null
}
```

**业务流程：**
1. 从 `Authorization` 头提取 Token
2. 在 Redis 中删除对应的 Token 记录

---

## 3. 用户模块 (User)

> 前缀: `/api/user/`
>
> 认证要求: 需要有效的 Bearer Token
>
> 权限要求: 任意已登录用户（教师/学生均可）

### 3.1 修改手机号

> **POST** `/api/user/change-phone`

**请求体：**

```json
{
  "phone": "13800138000"
}
```

| 参数 | 类型 | 必填 | 验证规则 |
|------|------|------|----------|
| phone | string | 是 | 中国大陆手机号格式 `/^1[3-9]\d{9}$/` |

**响应示例：**

```json
{
  "code": 0,
  "msg": "修改成功",
  "data": {
    "phone": "13800138000"
  }
}
```

**错误码：**
- `1002` - 手机号格式不正确
- `401` - 身份验证未通过

---

### 3.2 修改昵称

> **POST** `/api/user/change-nickname`

**请求体：**

```json
{
  "nickname": "新昵称"
}
```

| 参数 | 类型 | 必填 | 验证规则 |
|------|------|------|----------|
| nickname | string | 是 | 非空，最多 64 个字符（多字节） |

**响应示例：**

```json
{
  "code": 0,
  "msg": "修改成功",
  "data": {
    "nickname": "新昵称"
  }
}
```

**错误码：**
- `1003` - 昵称不能为空
- `1004` - 昵称长度不能超过 64 个字符
- `401` - 身份验证未通过

---

## 4. 班级模块 (Class)

> 前缀: `/api/class/`
>
> 认证要求: 需要有效的 Bearer Token
>
> 权限要求: **仅教师可用** (`role=1`)

### 4.1 创建班级

> **POST** `/api/class/create`

**请求体：**

```json
{
  "name": "数学提高班",
  "start_date": "2026-09-01",
  "end_date": "2026-12-31",
  "class_days": [1, 3, 5],
  "class_start_time": "09:00",
  "class_end_time": "10:30",
  "student_ids": [5, 8, 12]
}
```

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| name | string | 是 | 班级名称，最多 100 字符 |
| start_date | string | 是 | 课程开始日期，格式 `YYYY-MM-DD` |
| end_date | string | 是 | 课程结束日期，格式 `YYYY-MM-DD`，须大于 start_date |
| class_days | int[] | 是 | 每周上课日，`1`=周一 ~ `7`=周日 |
| class_start_time | string | 是 | 每日开始时间，格式 `HH:MM` |
| class_end_time | string | 是 | 每日结束时间，格式 `HH:MM`，须大于 class_start_time |
| student_ids | int[] | 否 | 初始 enrolled 学生 ID 列表 |

**响应示例：**

```json
{
  "code": 0,
  "msg": "创建成功",
  "data": {
    "id": 1
  }
}
```

**业务流程：**
1. 验证参数（名称、日期范围、上课日格式、时间格式）
2. 检查学生是否已被分配到其他班级
3. 开启事务：创建班级记录 + 分配学生
4. 同时更新 `users` 表的 `class_id` 字段

**错误码：**
- `2001` - 班级名称不能为空
- `2004` - 学生已被分配到其他班级
- `2006` - 开始日期不能晚于结束日期
- `2007` - 上课日格式错误

---

### 4.2 编辑班级

> **POST** `/api/class/update`

支持部分更新，仅传需要修改的字段。**只能编辑自己创建的班级。**

**请求体：**

```json
{
  "id": 1,
  "name": "数学提高班（升级版）",
  "start_date": "2026-09-01",
  "end_date": "2027-01-31",
  "class_days": [1, 2, 3, 4, 5],
  "class_start_time": "10:00",
  "class_end_time": "11:30"
}
```

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 是 | 班级 ID |
| name | string | 否 | 新班级名称 |
| start_date | string | 否 | 新课程开始日期 |
| end_date | string | 否 | 新课程结束日期 |
| class_days | int[] | 否 | 新课程上课日 |
| class_start_time | string | 否 | 新课程每日开始时间 |
| class_end_time | string | 否 | 新课程每日结束时间 |

**响应示例：**

```json
{
  "code": 0,
  "msg": "更新成功",
  "data": null
}
```

**错误码：**
- `2002` - 只有老师可以操作班级
- `2003` - 班级不存在
- `2006` - 开始日期不能晚于结束日期 / 开始时间不早于结束时间

---

### 4.3 班级列表

> **GET** `/api/class/list`

获取当前教师创建的所有班级（分页）。

**查询参数：**

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| page | int | 1 | 页码 |
| page_size | int | 10 | 每页条数（最大 100） |

**响应示例：**

```json
{
  "code": 0,
  "msg": "SUCCESS",
  "data": {
    "list": [
      {
        "id": 1,
        "name": "数学提高班",
        "start_date": "2026-09-01",
        "end_date": "2026-12-31",
        "class_days": "周一、周三、周五",
        "class_start_time": "09:00",
        "class_end_time": "10:30",
        "student_count": 5
      }
    ],
    "total": 12
  }
}
```

**字段说明：**
- `class_days`: 中文可读格式（如 "周一、周三、周五"）
- `student_count`: 实时学生数量（通过子查询统计 `class_students` 表）

---

### 4.4 班级详情

> **GET** `/api/class/detail`

获取指定班级的详细信息，包括 enrolled 学生列表。

**查询参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int | 是 | 班级 ID |

**响应示例：**

```json
{
  "code": 0,
  "msg": "SUCCESS",
  "data": {
    "id": 1,
    "name": "数学提高班",
    "teacher_id": 3,
    "start_date": "2026-09-01",
    "end_date": "2026-12-31",
    "class_days": "周一、周三、周五",
    "class_start_time": "09:00",
    "class_end_time": "10:30",
    "students": [
      {
        "id": 5,
        "name": "张三",
        "phone": "13800138000",
        "age": 10,
        "gender": "m"
      },
      {
        "id": 8,
        "name": "李四",
        "phone": "13900139000",
        "age": 11,
        "gender": "f"
      }
    ]
  }
}
```

**错误码：**
- `2003` - 班级不存在

---

### 4.5 添加学生到班级

> **POST** `/api/class/add-student`

将学生添加到指定班级。学生会先被检查可用性（不在其他班级中）。

**请求体：**

```json
{
  "class_id": 1,
  "student_ids": [5, 8, 12]
}
```

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| class_id | int | 是 | 目标班级 ID |
| student_ids | int[] | 是 | 学生 ID 列表 |

**响应示例：**

```json
{
  "code": 0,
  "msg": "添加成功",
  "data": null
}
```

**业务流程：**
1. 检查班级是否存在
2. 检查学生是否可用（不在其他班级）
3. 开启事务：
   - 向 `class_students` 表插入记录（`insertOrIgnore` 去重）
   - 更新 `users` 表的 `class_id` 字段

---

### 4.6 移除学生

> **POST** `/api/class/remove-student`

从指定班级中移除学生。

**请求体：**

```json
{
  "class_id": 1,
  "student_ids": [5, 8]
}
```

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| class_id | int | 是 | 班级 ID |
| student_ids | int[] | 是 | 学生 ID 列表 |

**响应示例：**

```json
{
  "code": 0,
  "msg": "移除成功",
  "data": null
}
```

**业务流程：**
1. 从 `class_students` 表中删除对应记录
2. 清除这些学生在该班级中的 `users.class_id`

---

### 4.7 获取所有学生

> **GET** `/api/class/students`

获取所有学生（包含已分配班级和未分配的）。

**响应示例：**

```json
{
  "code": 0,
  "msg": "SUCCESS",
  "data": [
    {
      "id": 5,
      "name": "张三",
      "phone": "13800138000",
      "age": 10,
      "gender": "m",
      "class_id": 1
    },
    {
      "id": 6,
      "name": "李四",
      "phone": "13900139000",
      "age": 11,
      "gender": "f",
      "class_id": 0
    }
  ]
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| class_id | int | `0` 表示未分配班级，否则为班级 ID |

---

### 4.8 获取未分配学生

> **GET** `/api/class/unassigned-students`

获取尚未分配到任何班级的学生（用于新建班级时选择）。

**响应示例：**

```json
{
  "code": 0,
  "msg": "SUCCESS",
  "data": [
    {
      "id": 15,
      "name": "王五",
      "phone": "13700137000",
      "age": 9,
      "gender": "m"
    }
  ]
}
```

**筛选条件：** `role=2`（学生）AND `class_id=0`

---

## 5. 错误码对照表

### 5.1 通用错误码

| code | 消息 | 说明 |
|------|------|------|
| 0 | SUCCESS | 成功 |
| 401 | 身份验证未通过 | Token 无效或过期 |
| 403 | 禁止访问 | 权限不足（非教师访问教师接口） |
| 404 | 访问路由不存在 | 路由未匹配 |
| 500 | Server Error | 服务器内部错误 |

### 5.2 用户相关

| code | 消息 | 说明 |
|------|------|------|
| 1002 | 手机号格式不正确 | 不符合中国大陆手机号格式 |
| 1003 | 昵称不能为空 | 昵称为空字符串 |
| 1004 | 昵称长度不能超过64个字符 | 昵称超出长度限制 |

### 5.3 班级相关

| code | 消息 | 说明 |
|------|------|------|
| 2001 | 班级名称不能为空 | 创建班级时名称为空 |
| 2002 | 只有老师可以操作班级 | 非教师角色访问班级接口 |
| 2003 | 班级不存在 | 指定的班级 ID 不存在 |
| 2004 | 学生已被分配到其他班级 | 学生已在其他班级中 |
| 2005 | 学生不存在 | 指定的学生 ID 不存在 |
| 2006 | 开始日期不能晚于结束日期 | 日期范围校验失败 |
| 2007 | 上课日格式错误 | 上课日不在 1-7 范围内 |

---

## 6. 中间件说明

### 6.1 TokenDecodeMiddleware（全局）

- **作用：** 解析并验证 Bearer Token
- **跳过路由：** `/api/auth/wechat-login`, `/favicon.ico`
- **流程：**
  1. 从 `Authorization: Bearer {token}` 提取 Token
  2. 校验 UUID v4 格式
  3. 在 Redis 中验证 Token 有效性
  4. 验证通过后，将用户 ID 写入 Hyperf Context（键名 `MemberId`）
  5. 使用 Lua 脚本实现滑动续期

### 6.2 AuthMiddleware（全局）

- **作用：** 角色权限校验
- **流程：**
  1. 路由未匹配 → 返回 404
  2. 用户未登录（Context 中无 `MemberId`） → 返回 401
  3. 访问 `/api/class/*` 路由 → 校验用户角色为教师（`role=1`），否则返回 403

---

## 附录：请求流程图

```
┌─────────────┐
│  小程序端    │
│  wx.login()  │
└──────┬──────┘
       │ code
       ▼
┌─────────────────────────────────────────────┐
│  POST /api/auth/wechat-login                │
│  → 调用微信 sns/jscode2session              │
│  → 获取 openid                              │
│  → 查找/创建用户                            │
│  → 生成 UUID Token 存入 Redis               │
│  → 返回 { token, user }                     │
└──────┬──────────────────────────────────────┘
       │ token
       ▼
┌─────────────────────────────────────────────┐
│  后续所有请求                                 │
│  Authorization: Bearer {token}               │
│                                             │
│  TokenDecodeMiddleware ──→ 验证 Token         │
│  AuthMiddleware     ──→ 角色校验              │
│  Controller         ──→ 业务处理              │
│  Service            ──→ 数据库操作             │
└─────────────────────────────────────────────┘
```
