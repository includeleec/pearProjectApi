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
        $data = $request::only('task_stage_code,type,pay_date,pay_amount,remark');

        if (!$request::post('task_stage_code')) {
            $this->error("task_stage_code is required");
        }

        if (!$request::post('type')) {
            $this->error("type is required");
        }

        try {
            $result = $this->model->createPayPlan($data['task_stage_code'], $data['type'], $data['pay_date'], $data['pay_amount'], $data['remark']);
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
        $data = $request::only('code,type,pay_date,pay_amount,remark');

        if (!$request::post('code')) {
            $this->error("code is required");
        }

        if (!$request::post('type')) {
            $this->error("type is required");
        }

        $pay_plan = $this->model->where(['code' => $data['code']])->field('code')->find();

        if (!$pay_plan) {
            $this->error("pay plan is not exist");
        }

        try {
            $result = $this->model->updatePayPlan($data['code'], $data);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $this->success('修改成功', $result);
        }
        $this->error("操作失败，请稍候再试！");

    }

    /**
     * 删除列表
     * @return void
     */
    public function remove()
    {
        $code = Request::post('code');
        if (!$code) {
            $this->error("请选择一个支付计划");
        }
        try {
            $result = $this->model->deletePayPlan($code);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $this->success('');
        }
    }
}
