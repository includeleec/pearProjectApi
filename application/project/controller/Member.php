<?php


namespace app\project\controller;
use app\common\Model\Member as MemberModel;

use controller\BasicApi;
use think\facade\Request;

class Member extends BasicApi
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->model) {
            $this->model = new MemberModel();
        }
    }

    public function list()
    {

        $where = [];

        $departmentId = Request::param('departmentId');
        if($departmentId) {
            $where[] = ['department_id','=',$departmentId];
        }


        $query = $this->model->field(['id','account','name','avatar','last_login_time','email','code','department_id'])->where($where);

        $list['list'] = $query->select();

        foreach ($list['list'] as &$item) {
            $item['department'] = $item->department;
        }


        $this->success('', $list);
    }

    public function searchInviteMember() {

        $departmentId = Request::param('departmentId');

        $query = $this->model->field(['id','account','name','avatar','last_login_time','email','code','department_id'])->where('department_id','NULL');

        $list = $query->select();

        $this->success('', $list);

    }

    /**
     *
     * 读取成员详细信息
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function read()
    {
        $id = Request::param('id');
        if (!$id) {
            $this->error("缺少参数");
        }
        $member = $this->model->where(['id' => $id])->find();

        $member['department'] = $member->department;

        $this->success('', $member);
    }


    /**
     * 账户编辑
     * @return array|string
     */
    public function edit()
    {
        //todo 权限判断

        $params = Request::only('mobile,email,desc,name,position,id,description');
        $result = $this->model->_edit($params, ['id' => $params['id']]);
        if ($result) {
            $this->success('');
        }
        $this->error("操作失败，请稍候再试！");
    }


    /**
     * 邀请成员
     */
    public function inviteMember()
    {
        //部门编号为空，则添加至组织
        $data = Request::only('memberId,departmentId');
        if (!$data['memberId']) {
            $this->error('数据异常');
        }
        try {
            $this->model->inviteMember($data['memberId'], $data['departmentId']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        $this->success('');
    }

    /**
     * 移除成员
     */
    public function removeMember()
    {
        $data = Request::only('memberId,departmentId');
        if (!$data['memberId'] || !$data['departmentId']) {
            $this->error('数据异常');
        }
        try {
            $this->model->removeMember($data['memberId'], $data['departmentId']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        $this->success('');
    }

}