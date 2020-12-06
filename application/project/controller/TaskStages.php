<?php

namespace app\project\controller;

use app\common\Model\PayPlan;
use controller\BasicApi;
use think\facade\Request;

/**
 */
class TaskStages extends BasicApi
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->model) {
            $this->model = new \app\common\Model\TaskStages();
        }
    }

    /**
     * 获取项目的所有阶段
     */
    public function index()
    {
        $where = [];
        $code = Request::post('projectCode');
        if (!$code) {
            $this->error("请选择一个项目");
        }
        $where[] = ['project_code', '=', $code];
        $list = $this->model->_list($where, 'sort asc,id asc');

//        $status = [1 => '正常', 2 => '滞后'];
        $now = date("Y-m-d h:i:s");

        if ($list['list']) {
            foreach ($list['list'] as &$item) {
//                $item['statusText'] = $status[$item['status']];
                $item['tasksLoading'] = true; //任务加载状态
                $item['fixedCreator'] = false; //添加任务按钮定位
                $item['showTaskCard'] = false; //是否显示创建卡片
                $item['tasks'] = [];
                $item['doneTasks'] = [];
                $item['unDoneTasks'] = [];
                $item['payPlan'] = PayPlan::where(['task_stage_id' => $item['id']])->find();

                // 该阶段是否滞后, default = false
                $item['delay'] = false;
                if($item['plan_date']) {
                    // 当前时间 >= 计划时间
                    if(strtotime($now) >= strtotime($item['plan_date'])) {
                        // 如果没有设置实际执行时间 execute_time 判断为滞后
                        if(!$item['execute_date']) {
                            $item['delay'] = true;
                        }
                        $list['current_stage'] = $item;
                    }
                }

            }
        }
        $this->success('', $list);
    }

    /**
     * 根据当前系统时间，计算项目所处阶段, 各阶段的是否滞后
     */
    public function calProject()
    {
        $where = [];
        $code = Request::post('projectCode');
        if (!$code) {
            $this->error("请选择一个项目");
        }
        $where[] = ['project_code', '=', $code];
        $list = $this->model->_list($where, 'sort asc');

        $now = date("Y-m-d h:i:s");

        if ($list['list']) {
            foreach ($list['list'] as &$item) {
                // unset status
                unset($item['status']);

                // 该阶段是否滞后, default = false
                $item['delay'] = false;

                if($item['plan_date']) {

                    // 当前时间 >= 计划时间
                    if(strtotime($now) >= strtotime($item['plan_date'])) {
                        // 如果没有设置实际执行时间 execute_time 判断为滞后
                        if(!$item['execute_date']) {
                            $item['delay'] = true;
                        }
                        $list['current_stage'] = $item;
                    }

                }
            }
        }

        $list['now'] = $now;

        $this->success('', $list);
    }

    public function _getAll()
    {
        $where = [];
        $code = Request::post('projectCode');
        if (!$code) {
            $this->error("请选择一个项目");
        }
        $where[] = ['project_code', '=', $code];
        $list = $this->model->where($where)->select();
        $this->success('', $list);
    }

    /**
     * 显示资源列表
     * @return void
     * @throws \think\exception\DbException
     */
    public function tasks()
    {
        $where = [];
        $code = Request::post('stageCode');
        $done = Request::param('done', -1);
        $title = Request::param('title', '');
        $pri = json_decode(Request::post('pri', ''));
        $status = json_decode(Request::post('status', ''));
        $stage = json_decode(Request::post('stage', ''));
        $executor = json_decode(Request::post('executor', ''));
        $creator = json_decode(Request::post('creator', ''));
        $joiner = json_decode(Request::post('joiner', ''));
        $endTime = json_decode(Request::post('endTime', ''));
        $beginTime = json_decode(Request::post('beginTime', ''));
        $createTime = json_decode(Request::post('createTime', ''));
        $doneTime = json_decode(Request::post('doneTime', ''));
        if (!$code) {
            $this->error("数据解析异常");
        }
        $where[] = ['stage_code', '=', $code];
        $list = $this->model->tasks($code, 0, $done, $title, $pri, $executor, $creator, $joiner, $status, $endTime, $beginTime, $createTime, $doneTime);
//        $list = \app\common\Model\Task::alias('t')->join('member m','t.assign_to = m.code')->field()->where(['stage_code'=>$code])->select();

        $this->success('', $list);
    }

    public function sort(Request $request)
    {
        $data = $request::only('preCode,nextCode');
        if (!$request::post('preCode')) {
            $this->error("参数有误");
        }
        try {
            $this->model->sort($data['preCode'], $data['nextCode']);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        $this->success();
    }

    /**
     * 新增
     * @param Request $request
     * @return void
     */
    public function save(Request $request)
    {
        $data = $request::only('name,projectCode');
        if (!$request::post('name')) {
            $this->error("请填写列表名称");
        }
        try {
            $result = $this->model->createStage($data['name'], $data['projectCode']);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $this->success('添加成功', $result);
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 保存
     * @param Request $request
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit(Request $request)
    {
        $data = $request::only('name,stageCode,plan_date,execute_date,status');
        if (!$request::post('name')) {
            $this->error("请填写列表名称");
        }
        if (!$data['stageCode']) {
            $this->error("请选择一个列表");
        }
        $template = $this->model->where(['code' => $data['stageCode']])->field('id')->find();
        if (!$template) {
            $this->error("该列表已失效");
        }

        $param =
            ['name' => $data['name'],
            'plan_date' => $data['plan_date'],
            'execute_date' => $data['execute_date'],
            'status' => $data['status'],
        ];

        $result = $this->model->_edit($param, ['code' => $data['stageCode']]);
        if ($result) {
            $this->success('');
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 删除列表
     * @return void
     */
    public function delete()
    {
        $code = Request::post('code');
        if (!$code) {
            $this->error("请选择一个列表");
        }
        try {
            $result = $this->model->deleteStage($code);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $this->success('');
        }
    }
}
