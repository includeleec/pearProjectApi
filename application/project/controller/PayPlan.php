<?php

namespace app\project\controller;

use controller\BasicApi;
use think\facade\Request;

/**
 */
class PayPlan extends BasicApi
{

    public function __construct()
    {
        parent::__construct();
        if (!$this->model) {
            $this->model = new \app\common\Model\PayPlan();
        }
    }

    /**
     * 新增
     * @param Request $request
     * @return void
     */
    public function save(Request $request)
    {
        $data = $request::only('task_stage_id,type,pay_date,pay_amount,remark');

        if (!$data['task_stage_id']) {
            $this->error("task_stage_id is required");
        }

        if (!$data['type']) {
            $this->error("type is required");
        }

        try {
            $result = $this->model->createPayPlan($data);
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
        $data = $request::only('task_stage_id,type,pay_date,pay_amount,remark');

//        if (!$request::post('id')) {
//            $this->error("id is required");
//        }

        if (!$data['task_stage_id']) {
            $this->error("task_stage_id is required");
        }

        if (!$request::post('type')) {
            $this->error("type is required");
        }

        $pay_plan = $this->model->where(['task_stage_id' => $data['task_stage_id']])->find();


        if (!$pay_plan) {
            // 不存在，创建新的支付计划
            $result = $this->model->createPayPlan($data);
            if($result) {
                $this->success('创建支付计划成功', $result);
            }

        } else {
            // 已存在，更新
            $result = $pay_plan->save($data);
            if($result) {
                $this->success('修改支付计划成功', $result);
            }

        }


    }

    /**
     * 删除列表
     * @return void
     */
    public function remove()
    {
        $task_stage_id = Request::post('task_stage_id');
        if (!$task_stage_id) {
            $this->error("请输入task_stage_id");
        }

        $pay_plan = $this->model->where(['task_stage_id' => $task_stage_id])->find();

        if(!$pay_plan) {
            $this->error("task_stage_id 对应支付计划不存在");
        }


        try {
//            $result = $this->model->deletePayPlan($pay_plan['id']);
            $result = $pay_plan->delete();
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }

        if ($result) {
            $this->success('删除支付计划成功');
        }
    }
}
