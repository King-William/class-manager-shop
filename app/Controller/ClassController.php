<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\ErrorCode;
use App\Service\ClassService;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use App\Middleware\AuthMiddleware;

#[Controller(prefix: '/api/class')]
#[Middleware(middleware: AuthMiddleware::class)]
class ClassController extends AbstractController
{
    #[Inject]
    protected ClassService $classService;

    /**
     * 创建班级.
     * POST /api/class/create
     */
    #[RequestMapping(path: 'create', methods: ['POST'])]
    public function create(): \Psr\Http\Message\ResponseInterface
    {
        $params = $this->request->post();

        try {
            $result = $this->classService->createClass($params, $this->currentTeacherId());
            return $this->success(['id' => $result['id']], '创建成功');
        } catch (\App\Exception\BusinessException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 编辑班级.
     * POST /api/class/update
     */
    #[RequestMapping(path: 'update', methods: ['POST'])]
    public function update(): \Psr\Http\Message\ResponseInterface
    {
        $params = $this->request->post();

        try {
            $this->classService->updateClass($params, $this->currentTeacherId());
            return $this->success(null, '更新成功');
        } catch (\App\Exception\BusinessException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 班级列表.
     * GET /api/class/list
     */
    #[RequestMapping(path: 'list', methods: ['GET'])]
    public function list(): \Psr\Http\Message\ResponseInterface
    {
        $page = (int) $this->request->query('page', 1);
        $pageSize = min((int) $this->request->query('page_size', 10), 100);

        try {
            $result = $this->classService->getClassList($this->currentTeacherId(), $page, $pageSize);
            return $this->success($result, 'SUCCESS');
        } catch (\App\Exception\BusinessException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 班级详情.
     * GET /api/class/detail
     */
    #[RequestMapping(path: 'detail', methods: ['GET'])]
    public function detail(): \Psr\Http\Message\ResponseInterface
    {
        $classId = (int) $this->request->query('id', 0);
        if ($classId <= 0) {
            return $this->error(ErrorCode::CLASS_NOT_FOUND, '班级ID不能为空');
        }

        try {
            $detail = $this->classService->getClassDetail($classId);
            return $this->success($detail, 'SUCCESS');
        } catch (\App\Exception\BusinessException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 添加学生到班级.
     * POST /api/class/add-student
     */
    #[RequestMapping(path: 'add-student', methods: ['POST'])]
    public function addStudent(): \Psr\Http\Message\ResponseInterface
    {
        $classId = (int) $this->request->input('post.class_id', 0);
        $studentIds = $this->request->input('post.student_ids', []);

        if ($classId <= 0) {
            return $this->error(ErrorCode::CLASS_NOT_FOUND, '班级ID不能为空');
        }

        if (!is_array($studentIds) || empty($studentIds)) {
            return $this->error(ErrorCode::STUDENT_NOT_FOUND, '学生ID不能为空');
        }

        try {
            $this->classService->addStudents($classId, $studentIds);
            return $this->success(null, '添加成功');
        } catch (\App\Exception\BusinessException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 移除学生.
     * POST /api/class/remove-student
     */
    #[RequestMapping(path: 'remove-student', methods: ['POST'])]
    public function removeStudent(): \Psr\Http\Message\ResponseInterface
    {
        $classId = (int) $this->request->input('post.class_id', 0);
        $studentIds = $this->request->input('post.student_ids', []);

        if ($classId <= 0) {
            return $this->error(ErrorCode::CLASS_NOT_FOUND, '班级ID不能为空');
        }

        if (!is_array($studentIds) || empty($studentIds)) {
            return $this->error(ErrorCode::STUDENT_NOT_FOUND, '学生ID不能为空');
        }

        try {
            $this->classService->removeStudents($classId, $studentIds);
            return $this->success(null, '移除成功');
        } catch (\App\Exception\BusinessException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 获取所有学生（含已分配班级的）.
     * GET /api/class/students
     */
    #[RequestMapping(path: 'students', methods: ['GET'])]
    public function students(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $list = $this->classService->getAllStudents();
            return $this->success($list, 'SUCCESS');
        } catch (\App\Exception\BusinessException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 获取未分配的学生（新建班级时用）.
     * GET /api/class/unassigned-students
     */
    #[RequestMapping(path: 'unassigned-students', methods: ['GET'])]
    public function unassignedStudents(): \Psr\Http\Message\ResponseInterface
    {
        try {
            $list = $this->classService->getUnassignedStudents();
            return $this->success($list, 'SUCCESS');
        } catch (\App\Exception\BusinessException $e) {
            return $this->error($e->getCode(), $e->getMessage());
        }
    }

    private function currentTeacherId(): int
    {
        return Context::get('MemberId', 0);
    }

    private function success(?array $data, string $msg = 'SUCCESS'): \Psr\Http\Message\ResponseInterface
    {
        return $this->response->json([
            'code' => ErrorCode::SUCCESS,
            'msg'  => $msg,
            'data' => $data,
        ]);
    }

    private function error(int $code, string $msg): \Psr\Http\Message\ResponseInterface
    {
        return $this->response->json([
            'code' => $code,
            'msg'  => $msg,
            'data' => null,
        ]);
    }
}
