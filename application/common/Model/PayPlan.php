<?php

namespace app\common\Model;

use Exception;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;

/**
 * 支付计划
 * Class Organization
 * @package app\common\Model
 */
class PayPlan extends CommonModel
{
    protected $append = ['type_text'];


    // 支付计划类型
    public function getTypeTextAttr($value,$data)
    {
        $status = [1=>'首款',2=>'进度款1',3=>'进度款2',4=>'尾款',5=>'质保金'];
        return $status[$data['type']];
    }

    public function createPayPlan($data)
    {

        try {

            if (!$data['task_stage_id']) {
                throw new \Exception('task_stage_id required', 1);
            }

            if (!$data['type']) {
                throw new \Exception('type required', 1);
            }

            $stage = TaskStages::where(['id' => $data['task_stage_id']])->field('id')->find();
            if (!$stage) {
                throw new \Exception('找不到对应的任务列表 task stage id', 2);
            }

            $alreay_link_task_stage = self::where(['task_stage_id' => $data['task_stage_id']])->find();

            if ($alreay_link_task_stage) {
                throw new \Exception('已经存在支付计划关联到 task_stage_id', 3);
            }

            $data = [
                'task_stage_id' => $data['task_stage_id'],
                'code' => createUniqueCode('payPlan'),
                'type' => $data['type'],
                'pay_date' => $data['pay_date'],
                'pay_amount' => $data['pay_amount'],
                'remark' => $data['remark'],
            ];

            $result = self::create($data);

            // $result = $data;
//            if ($result) {
//                unset($result['id']);
//
//            }
            return $result;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

    }

    /**
     * update pay plan
     * @param $id
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function updatePayPlan($id, $data)
    {
        if (!$id) {
            throw new \Exception('请选择支付计划', 1);
        }
        $item = self::where(['id' => $id])->field('id')->find();
        if (!$item) {
            throw new \Exception('没有找到对应的支付计划', 1);
        }
        $result = self::update($data, ['id' => $id]);
        return $result;
    }

    /**
     * delete pay plan
     * @param $code
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function deletePayPlan($id)
    {
        $stage = self::where(['id' => $id])->field('id')->find();
        if (!$stage) {
            throw new \Exception('pay plan is not exist', 1);
        }

        $result = self::destroy(['id' => $id]);

        if (!$result) {
            throw new \Exception('删除失败', 3);
        }
        return $result;
    }
}
