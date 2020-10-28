<?php

namespace app\common\Model;

use Exception;

/**
 * 合同 model
 * Class Organization
 * @package app\common\Model
 */
class Contract extends CommonModel
{
    protected $append = [];

    public function createContractToProject($data)
    {

        try {

            // 合同名称
            if (!$data['name']) {
                throw new \Exception('name required', 1);
            }

            // 合同编号
            if (!$data['number']) {
                throw new \Exception('number required', 2);
            }

            // 合同金额
            if (!$data['amount']) {
                throw new \Exception('amount required', 3);
            }

            // 合同关联的 project
            if (!$data['project_code']) {
                throw new \Exception('project_code required', 3);
            }

            // project_code 是否可以查询到对应的 project
            $project_code = Project::where(['code' => $data['project_code']])->field('id')->find();

            if (!$project_code) {
                throw new \Exception('没有找到对应的 project', 404);
                // throw error(4, '没有找到对应的 contract');
            }

            // 是否已经绑定过 project
            $already_bind_project = self::where(['project_code' => $data['project_code']])->field('id')->find();

            if ($already_bind_project) {
                throw new \Exception('project code 已经被 contract 绑定', 5);
            }

            // 生成合同编码
            $data['code'] = createUniqueCode('contract');

            $result = self::create($data);

            if ($result) {
                unset($result['id']);
            }

            return $result;

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }

    }

    public function updateContract($data)
    {

        // 合同code
        if (!$data['code']) {
            throw new \Exception('code required', 1);
        }

        // 合同名称
        if (!$data['name']) {
            throw new \Exception('name required', 1);
        }

        // 合同编号
        if (!$data['number']) {
            throw new \Exception('number required', 2);
        }

        // 合同金额
        if (!$data['amount']) {
            throw new \Exception('amount required', 3);
        }

        // project_code 是否可以查询到对应的 project
        // $project_code = Project::where(['code' => $data['project_code']])->field('id')->find();

        // if (!$project_code) {
        //     throw new \Exception('没有找到对应的 project', 404);
        // }

        // 是否已经绑定过 project
        // $already_bind_project = self::where(['project_code' => $data['project_code']])->field('id')->find();

        // if ($already_bind_project) {
        //     throw new \Exception('project code 已经被 contract 绑定', 5);
        // }
        $code = $data['code'];

        $item = self::where(['code' => $code])->field('id')->find();

        if (!$item) {
            throw new \Exception('没有找到对应的 contract', 5);
        }

        unset($data['code']);

        $result = self::update($data, ['code' => $code]);
        return $result;
    }

    public function deleteContract($code)
    {

        if (!$code) {
            throw new \Exception('code required', 1);
        }

        $item = self::where(['code' => $code])->field('id')->find();

        if (!$item) {
            throw new \Exception('没有找到对应的 contract', 3);
        }

        $result = self::destroy(['code' => $code]);

        if (!$result) {
            throw new \Exception('删除失败', 3);
        }

        return $result;
    }
}
