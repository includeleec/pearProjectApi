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
    protected $append = [];

    public function createPayPlan($data)
    {

        try {

            if (!$data['task_stage_code']) {
                throw new \Exception('task_stage_code required', 1);
            }

            if (!$data['type']) {
                throw new \Exception('type required', 1);
            }

            $stage = TaskStages::where(['code' => $data['task_stage_code']])->field('code')->find();
            if (!$stage) {
                throw new \Exception('找不到对应的任务列表 task stage code', 2);
            }

            $alreay_link_task_stage = self::where(['task_stage_code' => $data['task_stage_code']])->find();

            if ($alreay_link_task_stage) {
                throw new \Exception('已经存在支付计划关联到 task_stage_code', 3);
            }

            $data = [
                'task_stage_code' => $data['task_stage_code'],
                'code' => createUniqueCode('payPlan'),
                'type' => $data['type'],
                'pay_date' => $data['pay_date'],
                'pay_amount' => $data['pay_amount'],
                'remark' => $data['remark'],
            ];

            $result = self::create($data);

            // $result = $data;

            if ($result) {
                unset($result['id']);

            }
            return $result;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

    }

    /**
     * update pay plan
     * @param $code
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function updatePayPlan($code, $data)
    {
        if (!$code) {
            throw new \Exception('请选择支付计划', 1);
        }
        $item = self::where(['code' => $code])->field('id')->find();
        if (!$item) {
            throw new \Exception('没有找到对应的支付计划', 1);
        }
        $result = self::update($data, ['code' => $code]);
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
    public function deletePayPlan($code)
    {
        $stage = self::where(['code' => $code])->field('id')->find();
        if (!$stage) {
            throw new \Exception('pay plan is not exist', 1);
        }

        $result = self::destroy(['code' => $code]);

        if (!$result) {
            throw new \Exception('删除失败', 3);
        }
        return $result;
    }
}
