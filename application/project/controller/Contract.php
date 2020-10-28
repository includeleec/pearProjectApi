<?php

namespace app\project\controller;

use controller\BasicApi;
use think\facade\Request;

/**
 */
class Contract extends BasicApi
{

    public function __construct()
    {
        parent::__construct();
        if (!$this->model) {
            $this->model = new \app\common\Model\Contract();
        }
    }

    public function save(Request $request)
    {

        $data = $request::only('name,assign_date,limitation,perfomance_start_date, performance_end_date,amount,warranty,warranty_period,contact_name,contact_mobile,terms,number,project_code');

        if (!$request::post('project_code')) {
            $this->error("project_code is required");
        }

        if (!$request::post('name')) {
            $this->error("name is required");
        }

        if (!$request::post('number')) {
            $this->error("number is required");
        }

        if (!$request::post('amount')) {
            $this->error("amount is required");
        }

        try {
            $result = $this->model->createContractToProject($data);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $this->success('添加成功', $result);
        }
        $this->error("操作失败，请稍候再试！");
    }

    public function edit(Request $request)
    {
        $data = $request::only('code,name,assign_date,limitation,perfomance_start_date, performance_end_date,amount,warranty,warranty_period,contact_name,contact_mobile,terms,number');

        if (!$request::post('code')) {
            $this->error("code is required");
        }

        if (!$request::post('name')) {
            $this->error("name is required");
        }

        if (!$request::post('number')) {
            $this->error("number is required");
        }

        if (!$request::post('amount')) {
            $this->error("amount is required");
        }

        try {
            $result = $this->model->updateContract($data);
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
            $this->error("请选择一个 contract");
        }
        try {
            $result = $this->model->deleteContract($code);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $this->success('');
        }
    }
}
