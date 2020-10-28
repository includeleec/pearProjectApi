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

    /**
     * @param $stageCode
     * @param $type
     * @param $pay_date
     * @param $pay_amount
     * @param $remark

     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function createPayPlan($stageCode, $type, $pay_date, $pay_amount, $remark)
    {

        try {
            $stage = TaskStages::where(['code' => $stageCode])->field('code')->find();
            if (!$stage) {
                return error(1, '该任务列表无效');
            }

            if (!$type) {
                return error(2, 'type required');
            }

            $data = [
                'task_stage_code' => $stageCode,
                'code' => createUniqueCode('payPlan'),
                'type' => $type,
                'pay_date' => $pay_date,
                'pay_amount' => $pay_amount,
                'remark' => $remark,
            ];

            $result = self::create($data);

            // $result = $data;

            if ($result) {
                unset($result['id']);

            }
            return $result;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 1);
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
