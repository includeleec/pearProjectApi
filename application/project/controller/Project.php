<?php

namespace app\project\controller;

use app\common\Model\Contract;
use app\common\Model\Department;
use app\common\Model\Member;
use app\common\Model\ProjectCollection;
use app\common\Model\ProjectLog;
use app\common\Model\ProjectMember;
use app\common\Model\ProjectReport;
use app\common\Model\TaskStages;
use app\common\Model\ProjectTemplate;
use app\common\Model\TaskStagesTemplate;
use controller\BasicApi;
use service\DateService;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\Exception\DbException;
use think\facade\Request;
use think\File;
use think\facade\Validate;

/**
 */
class Project extends BasicApi
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->model) {
            $this->model = new \app\common\Model\Project();
        }
    }

    // 项目列表
    public function index()
    {
        $selectBy = Request::param('selectBy', 'all');
        $page = Request::param('page', 1);
        $pageSize = Request::param('pageSize', 10);
        $departmentId = Request::param('departmentId', 'all');

        $where = [];

        switch ($selectBy) {
            // 我负责的项目
            case 'my':
                $deleted = 0;
                $archive = 0;

                $where = [
                    ['archive', '=', $archive],
                    ['deleted', '=', $deleted]
                ];


                break;

            // 我收藏的项目
            case 'collect':
                $deleted = 0;
                $archive = -1;
                $collection = 1;
                break;
            // 已归档
            case 'archive':
                $deleted = 0;
                $archive = 1;
                $collection = -1;

                $where = [
                    ['archive', '=', $archive],
                    ['deleted', '=', $deleted]
                ];

                break;
            // 已删除
            case 'deleted':
                $deleted = 1;
                $where = [
                    ['deleted', '=', $deleted]
                ];
                break;
            // all
            default:
                $deleted = 0;
                $archive = 0;
                $collection = -1;

                $where = [
                    ['archive', '=', $archive],
                    ['deleted', '=', $deleted]
                ];

        }

//        if ($selectBy === 'all') {
//            $list = $this->model->getProjects($deleted, $archive, $collection, Request::post('page'), Request::post('pageSize'));
//        } else {
//            $list = $this->model->getMemberProjects(getCurrentMember()['code'], getCurrentOrganizationCode(), $deleted, $archive, $collection, Request::post('page'), Request::post('pageSize'));
//        }

        $currMember = getCurrentMember();
        $member = Member::get($currMember['id']);

        if($departmentId !== 'all') {
            $department = Department::where('id', $departmentId)->find();
            // 存在对应的查找部门
            if($department) {
                array_push($where,[
                    'belong_department_id', '=', $departmentId
                ]);
            }
        }


        if($selectBy === 'my') {

            $list['list'] = $member->belongsProject()->where($where)->page($page, $pageSize)->order('id', 'asc')->select();
        } else if($selectBy === 'collect') {
            $list['list'] = $member->collectProject()->select();
            $this->success('', $list);

        } else {
            $list['list'] = $this->model->where($where)->page($page, $pageSize)->order('id', 'asc')->select();
        }

        $status = [1 => '正常', 2 => '滞后'];

        if ($list['list']) {

            foreach ($list['list'] as $key => &$item) {

                $item['owner_name'] = '-';
                if (isset($item['project_code'])) {
                    $item['code'] = $item['project_code'];
                    $item = $this->model->where(['code' => $item['code']])->find();
                }
                $collected = ProjectCollection::where(['project_code' => $item['code'], 'member_code' => getCurrentMember()['code']])->field('id')->find();
                $item['collected'] = $collected ? 1 : 0;
                $owner = ProjectMember::alias('pm')->leftJoin('member m', 'pm.member_code = m.code')->where(['pm.project_code' => $item['code'], 'is_owner' => 1])->field('member_code,name')->find();
                $item['owner_name'] = $owner['name'];
                $item['statusText'] = $status[$item['status']];

                // 当前任务阶段(人工设置)
                // $item['current_task_stage'] = $item->currentTaskStage;

                $item['delay'] = false;

                // 获取该项目所有阶段 list
                $ts_list = TaskStages::where('project_code', $item['code'])->order('id', 'asc')->select();

                $now = date("Y-m-d h:i:s");
                // 遍历每个项目的所有阶段
                if ($ts_list) {
                    foreach ($ts_list as &$ts_item) {

                        // unset status
                        unset($ts_item['status']);

                        // 该阶段是否滞后, default = false
                        $ts_item['delay'] = false;

                        if($ts_item['plan_date']) {
                            // 当前时间 >= 计划时间
                            if(strtotime($now) >= strtotime($ts_item['plan_date'])) {
                                // 如果没有设置实际执行时间 execute_time 判断为滞后
                                if(!$ts_item['execute_date']) {
                                    $ts_item['delay'] = true;
                                    $item['delay'] = true;
                                } else {
                                    $item['delay'] = false;
                                }
                                // 项目的当前阶段(自动计算)
                                $item['current_stage'] = $ts_item;
                            }

                        }
                    }
                }

                //项目负责人
                $item['belong_member'] = $item->belongMember;

                //负责部门
                $item['belong_department'] = $item->belongDepartment;

                //项目合同
                $item['contract'] = $item->contract;

            }
            unset($item);

            // total list count
            $list['total'] = count($list['list']);

            // 滞后的项目数量
            $delay_project_count = 0;

            foreach ($list['list'] as $item) {
                if($item['delay']) {
                    $delay_project_count++;
                }
            }

            $list['delay_total'] = $delay_project_count;
        }


        $this->success('', $list);
    }

    // 项目统计
    public function statistic(Request  $request)
    {

        $deleted = 0;
        $archive = 0;

        $where = [
            ['archive', '=', $archive],
            ['deleted', '=', $deleted]
        ];

        $data = [];



        // get all projects
        $list = $this->model->where($where)->select();

        // 各部门的统计
        // get all departments
        $departments = Department::where('organization_code',  '')->field('id,name')->select();
        // 项目数量, 滞后的项目数量
        $data['dept'] = $departments;

        // init
        foreach ($data['dept'] as $key => &$dep_item) {
            // 各部门的总项目数量
            $dep_item['total'] = 0;
            // 各部门的滞后项目数量
            $dep_item['delay_total'] = 0;
        }

        # 找到对应的 project template
        $project_template_name = '专项研究课题';
        $project_template = ProjectTemplate::where('name',$project_template_name)->find();
        $projrect_stages = TaskStagesTemplate::where(['project_template_code' => $project_template['code']])->order('sort desc,id asc')->field('id,name')->select();

        // 各阶段
        $data['stages'] = $projrect_stages;
        foreach ($data['stages'] as &$d_stage) {
            $d_stage['count'] = 0;
            $d_stage['delay_count'] = 0;
        }

        if ($list) {
            foreach ($list as $key => &$item) {
                $item['owner_name'] = '-';
                if (isset($item['project_code'])) {
                    $item['code'] = $item['project_code'];
                    $item = $this->model->where(['code' => $item['code']])->find();
                }

                $owner = ProjectMember::alias('pm')->leftJoin('member m', 'pm.member_code = m.code')->where(['pm.project_code' => $item['code'], 'is_owner' => 1])->field('member_code,name')->find();
                $item['owner_name'] = $owner['name'];

                // 当前任务阶段(人工设置)
                // $item['current_task_stage'] = $item->currentTaskStage;

                $item['delay'] = false;

                // 获取该项目所有阶段 list
                $ts_list = TaskStages::where('project_code', $item['code'])->order('id', 'asc')->select();

                $now = date("Y-m-d h:i:s");
                // 遍历每个项目的所有阶段
                if ($ts_list) {
                    foreach ($ts_list as &$ts_item) {

                        // unset status
                        unset($ts_item['status']);

                        // 该阶段是否滞后, default = false
                        $ts_item['delay'] = false;

                        if($ts_item['plan_date']) {
                            // 当前时间 >= 计划时间
                            if(strtotime($now) >= strtotime($ts_item['plan_date'])) {
                                // 如果没有设置实际执行时间 execute_time 判断为滞后
                                if(!$ts_item['execute_date']) {
                                    $ts_item['delay'] = true;
                                    $item['delay'] = true;
                                } else {
                                    $item['delay'] = false;
                                }
                                // 项目的当前阶段(自动计算)
                                $item['current_stage'] = $ts_item;
                            }

                        }
                    }
                }

                //项目负责人
                $item['belong_member'] = $item->belongMember;

                //负责部门
                $item['belong_department'] = $item->belongDepartment;

                //项目合同
//                $item['contract'] = $item->contract;

            }
            unset($item);



            // 滞后的项目数量
            $delay_project_count = 0;

            // 遍历项目
            foreach ($list as $item) {

                // 遍历部门
                foreach ($data['dept'] as $key => &$dep_item) {
                    if($dep_item['id'] == $item['belong_department']['id']) {
                        $dep_item['total'] = $dep_item['total'] + 1;

                        if($item['delay']) {
                            $dep_item['delay_total'] = $dep_item['delay_total'] + 1;
                        }

                        break;
                    }

                }
                if($item['delay']) {
                    $delay_project_count++;
                }

                // 存在 current_stage
                if(isset($item['current_stage'])) {
                    // 遍历各阶段
                    foreach ($data['stages'] as &$d_stage) {
                        if($d_stage['name'] == $item['current_stage']['name']) {
                            $d_stage['count'] = $d_stage['count'] + 1;
                            break;
                        }
                    }
                }

            }

            // 总体
            // 项目数量
            $data['total'] = count($list);

            // 滞后的项目数量
            $data['delay_total'] = $delay_project_count;
        }


        // 各阶段滞后的项目数量

        // 项目金额




        $this->success("",$data);

    }

    public function analysis(Request $request)
    {
        $organizationCode = getCurrentOrganizationCode();
        $projectList = [];
        $monthNum = date('m', time());
        $monthList = DateService::lastCurrentMonth($monthNum);
        $monthList = array_reverse($monthList);
        foreach ($monthList as $key => $mounth) {
            if ($key < $monthNum - 1) {
                $num = \app\common\Model\Project::where('create_time', 'between', [date('Y-m-d H:i:s', $mounth['current_date']), date('Y-m-d H:i:s', $monthList[$key + 1]['current_date'])])->where(['deleted' => 0])->where(['organization_code' => $organizationCode])->count('id');
            } else {
                $num = \app\common\Model\Project::where('create_time', '>=', date('Y-m-d H:i:s', $mounth['current_date']))->where(['deleted' => 0])->where(['organization_code' => $organizationCode])->count('id');
            }
            $projectList[] = [
                '日期' => date('m', $mounth['current_date']) . '月',
                '数量' => $num,
            ];
        }
        $projectAll = \app\common\Model\Project::where(['deleted' => 0])->where(['organization_code' => $organizationCode])->select();
        $projectCount = count($projectAll);
        $projectSchedule = 0;
        $scheduleAll = 0;
        if ($projectAll) {
            foreach ($projectAll as $item) {
                $scheduleAll += $item['schedule'];
            }
        }
        if ($projectCount) {
            $projectSchedule = round($scheduleAll / ($projectCount * 100), 2);
        }
        $taskList = [];
        $currentMonth = DateService::lastCurrentMonth();
        $currentMonthBegin = $currentMonth[0]['current_date'];
        $today = date('d', time());
        $month = date('m', time());
        for ($i = 0; $i < $today; $i++) {
            $dayBegin = date('Y-m-d H:i:s', $currentMonthBegin + $i * DateService::DAY);
            $dayEnd = date('Y-m-d H:i:s', $currentMonthBegin + ($i + 1) * DateService::DAY);
            $taskList[] = [
                '日期' => $month . '月' . ($i + 1) . '日',
                '任务' => \app\common\Model\Task::alias('t')->leftJoin('project p', 't.project_code = p.code')->where('t.create_time', 'between', [$dayBegin, $dayEnd])->where(['t.deleted' => 0])->where(['p.organization_code' => $organizationCode])->count('t.id'),
            ];
        }

        $taskAll = \app\common\Model\Task::alias('t')->leftJoin('project p', 't.project_code = p.code')->where(['p.organization_code' => $organizationCode])->where(['t.deleted' => 0])->field('t.done,t.end_time,t.code')->select();
        $taskCount = count($taskAll);
        $taskOverdueCount = 0;
        $now = nowTime();
        foreach ($taskAll as $item) {
            if ($item['end_time']) {
                if (!$item['done']) {
                    $item['end_time'] < $now && $taskOverdueCount++;
                } else {
                    $log = ProjectLog::where(['action_type' => 'task', 'source_code' => $item['code'], 'type' => 'done'])->order('id desc')->find();
                    if ($log && $log['create_time'] > $item['end_time']) {
                        $taskOverdueCount++;
                    }
                }
            }
        }
        $taskOverduePercent = 0;
        if ($taskCount) {
            $taskOverduePercent = round($taskOverdueCount / $taskCount, 2) * 100;
        }
        $this->success('', compact('projectList', 'projectCount', 'projectSchedule', 'taskList', 'taskCount', 'taskOverdueCount', 'taskOverduePercent'));

    }

    /**
     * 获取自己的项目
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function selfList()
    {
        $type = Request::post('type', 0);
        $archive = Request::param('archive', 0);
        $delete = Request::param('delete');
        $organizationCode = Request::param('organizationCode', '');
        $memberId = Request::post('memberId', '');
        if (!$memberId) {
            $member = getCurrentMember();
        } else {
            $member = Member::where(['id' => $memberId])->find();
        }
        if (!$member) {
            $this->error("参数有误");
        }
        $deleted = $delete === null ? 1 : $delete;
        if (!$type) {
            $deleted = 0;
        }
        $list = $this->model->getMemberProjects($member['code'], '', $deleted, $archive, -1, Request::post('page'), Request::post('pageSize'));

        $status = [1 => '正常', 2 => '滞后'];

        if ($list['list']) {
            foreach ($list['list'] as $key => &$item) {
                $item['owner_name'] = '-';
                if (isset($item['project_code'])) {
                    $item['code'] = $item['project_code'];
                    $item = $this->model->where(['code' => $item['code']])->find();
                }
                $collected = ProjectCollection::where(['project_code' => $item['code'], 'member_code' => getCurrentMember()['code']])->field('id')->find();
                $item['collected'] = $collected ? 1 : 0;
                $owner = ProjectMember::alias('pm')->leftJoin('member m', 'pm.member_code = m.code')->where(['pm.project_code' => $item['code'], 'is_owner' => 1])->field('member_code,name')->find();
                $item['owner_name'] = $owner['name'];

                $item['statusText'] = $status[$item['status']];
            }
            unset($item);
        }
        $this->success('', $list);
    }

    /**
     * 新增
     *
     * @param Request $request
     * @return void
     * @throws \Exception
     */
    public function save(Request $request)
    {
        $data = $request::only('name,description,templateCode');
        if (!$request::post('name')) {
            $this->error("请填写项目名称");
        }
//        $data['organization_code'] = getCurrentOrganizationCode();
        $member = getCurrentMember();
        try {
            $result = $this->model->createProject($member['code'], $data['name'], $data['description'], $data['templateCode']);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $this->success('', $result);
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 新增项目
     * - 项目基本信息
     * - 设置项目合同信息
     * - 选择项目模板, 创建 task stages
     * - 设置每个阶段的 plan date, execute date
     */

    public function add_from_api(Request $request)
    {
        $data = Request::param();
        $validate = Validate::make([
            'belong_member_name' => 'require',
            'name' => 'require',
        ], [
            'belong_member_name.require' => '负责人 name 不能为空！',
            'name.require' => '项目名不能为空！',
        ]);

        $validate->check($data) || $this->error($validate->getError());

//        $data['task_stages'] = json_decode($data['task_stages'], true);
//        $this->success($data);

        $member = Member::where(['name' => $data['belong_member_name']])->find();
        if (!$member) {
            $this->error('不存在的 member name');
        }

        // 检查同名项目是否已经存在
        $project = $this->model->where('name', $data['name'])->find();
        if($project) {
            $this->success('同名项目已经存在');
        }

        # 找到对应的 project template
        $project_template_name = '专项研究课题';
        $project_template = ProjectTemplate::where('name',$project_template_name)->find();
        if(!$project_template) {
            $this->error('不存在的 project template:'.$project_template_name);
        }

        $data['project_template_code'] = $project_template['code'];

        try {
            $result = $this->model->createProjectFromAPI($member, $data);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $this->success('', $result);
        }
        $this->error("操作失败，请稍候再试！");


        $this->success('添加项目成功');
    }


    /**
     * 设为项目负责人
     * @param Request $request
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function setBelongMember(Request $request)
    {
        $code = $request::param('code');
        $belong_member_id = $request::param('belong_member_id');

        if (!$code) {
            $this->error('请填写项目code');
        }

        if (!$belong_member_id) {
            $this->error('请填写项目负责人id');
        }
        $project = $this->model->where('code', $code)->field('id', true)->find();

        if (!$project) {
            $this->notFound();
        }

        $member = Member::get($belong_member_id);
        if (!$member) {
            $this->error('用户id不存在');
        }

        $project->belong_member_id = $member['id'];
        $result = $project->save();
        if ($result) {
            $this->success('更新负责人成功');
        }
    }


    /**
     * 设为当前执行阶段
     * @param Request $request
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function setCurrentTaskStage(Request $request)
    {
        $code = $request::param('code');
        $current_task_stage_id = $request::param('current_task_stage_id');

        if (!$code) {
            $this->error('请填写项目code');
        }

        if (!$current_task_stage_id) {
            $this->error('请填写当前执行阶段id');
        }
        $project = $this->model->where('code', $code)->field('id', true)->find();

        if (!$project) {
            $this->error('项目不存在');
        }

        $taskStage = TaskStages::get($current_task_stage_id);
        if (!$taskStage) {
            $this->error('项目阶段不存在');
        }

        if ($taskStage->project->code != $project['code']) {
            $this->error('项目阶段id 与项目id 对应关系不存在');
        }

        $project->current_task_stage_id = $taskStage['id'];
        $result = $project->save();
        if ($result) {
            $this->success('更新当前项目阶段成功');
        }
    }


    /**
     * 获取单个项目信息
     *
     * @param Request $request
     * @return void
     * @throws DbException
     */
    public function read(Request $request)
    {
        $project = $this->model->where(['code' => $request::post('projectCode')])->field('id', true)->find();
        if (!$project) {
            $this->error('项目不存在');
        }
        $project['collected'] = 0;
        $collected = ProjectCollection::where(['project_code' => $project['code'], 'member_code' => getCurrentMember()['code']])->field('id')->find();
        if ($collected) {
            $project['collected'] = 1;
        }

        $status = [1 => '正常', 2 => '滞后'];

        $project['statusText'] = $status[$project['status']];

        $owner = ProjectMember::where(['project_code' => $project['code'], 'is_owner' => 1])->field('member_code')->find();
        if ($owner) {
            $member = Member::where(['code' => $owner['member_code']])->field('name,avatar')->find();
            if ($member) {
                $project['owner_name'] = $member['name'];
                $project['owner_avatar'] = $member['avatar'];
            }
        }

        // 当前任务阶段
//        $item['current_task_stage'] = $project->currentTaskStage;

        // 获取该项目所有阶段 list
        $ts_list = TaskStages::where('project_code', $project['code'])->order('id', 'asc')->select();

        $now = date("Y-m-d h:i:s");

        if ($ts_list) {
            foreach ($ts_list as &$ts_item) {
                // unset status
                unset($ts_item['status']);

                // 该阶段是否滞后, default = false
                $ts_item['delay'] = false;

                if($ts_item['plan_date']) {
                    // 当前时间 >= 计划时间
                    if(strtotime($now) >= strtotime($ts_item['plan_date'])) {
                        // 如果没有设置实际执行时间 execute_time 判断为滞后
                        if(!$ts_item['execute_date']) {
                            $ts_item['delay'] = true;
                        }
                        // 项目的当前阶段(自动计算)
                        $project['current_stage'] = $ts_item;
                    }

                }
            }
        }



        //项目负责人
        $project['belong_member'] = $project->belongMember;


        //负责部门
        $project['belong_department'] = $project->belongDepartment;

        //项目合同
        $project['contract'] = $project->contract;


        $this->success('', $project);
    }

    /**
     * 更新项目信息
     *
     * @param Request $request
     * @return void
     * @throws \Exception
     */
    public function edit(Request $request)
    {
        $data = $request::only('name,description,cover,private,prefix,open_prefix,schedule,
        open_begin_time,open_task_private,task_board_theme,begin_time,end_time,auto_update_schedule,excel_id,set_up_year,belong_department_id,belong_member_id,
        apply_set_up_date,annual_assignment_date,annual_assignment_batch,
        bidding_plan_submission_date,bidding_code,bidding_batch,bidding_amount,bidding_evaluation_date,winning_bid_accept_date,winning_bid_name,status,current_task_stage_id');
        $code = $request::param('projectCode');
        try {
            $result = $this->model->edit($code, $data);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());

        }
        if ($result) {
            $this->success('修改成功');
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 相关的项目动态
     */
    public function getLogBySelfProject()
    {
        $projectCode = Request::param('projectCode', '');
        $orgCode = getCurrentOrganizationCode();
        $member = getCurrentMember();
        $memberCode = $member['code'];
        if (!$member) {
            $this->success('', []);
        }
        $prefix = config('database.prefix');
        if (!$projectCode) {
            $where = [];
            $where[] = ['member_code', '=', $member['code']];
            $projectCodes = ProjectMember::where($where)->column('project_code');
            $sql = "select pp.code from {$prefix}project as pp join {$prefix}project_member as pm on pm.project_code = pp.code where pp.organization_code = '{$orgCode}' and (pm.member_code = '{$memberCode}') and pp.deleted = 0 group by pp.`code`";
            $projectCodes = Db::query($sql);
            if (!$projectCodes) {
                $this->success('', []);
            }
            foreach ($projectCodes as &$projectCode) {
                $projectCode = $projectCode['code'];
                $projectCode = "'{$projectCode}'";
            }
            $projectCodes = implode(',', $projectCodes);
            $sql = "select tl.remark as remark,tl.content as content,tl.is_comment as is_comment,tl.create_time as create_time,p.name as project_name,t.name as task_name,t.code as source_code,p.code as project_code,m.avatar as member_avatar,m.name as member_name from {$prefix}project_log as tl join {$prefix}task as t on tl.source_code = t.code join {$prefix}project as p on t.project_code = p.code join {$prefix}member as m on tl.member_code = m.code where tl.action_type = 'task' and p.code in ({$projectCodes}) and p.deleted = 0 order by tl.id desc limit 0,20";
//        $sql = "select tl.remark as remark,tl.content as content,tl.is_comment as is_comment,tl.create_time as create_time,p.name as project_name,p.code as project_code,m.avatar as member_avatar,m.name as member_name from {$prefix}project_log as tl join {$prefix}project as p on tl.project_code = p.code join {$prefix}member as m on tl.member_code = m.code where p.code in ({$projectCodes}) and p.deleted = 0 order by tl.id desc limit 0,20";
            $list = Db::query($sql);
        } else {
            $page = Request::param('page');
            $pageSize = Request::param('pageSize');
            if ($page < 1) {
                $page = 1;
            }
            $offset = $pageSize * ($page - 1);
            $sql = "select tl.type as type,tl.action_type as action_type,tl.source_code as source_code,tl.remark as remark,tl.content as content,tl.is_comment as is_comment,tl.create_time as create_time,p.name as project_name,p.code as project_code,m.avatar as member_avatar,m.name as member_name from {$prefix}project_log as tl join {$prefix}project as p on tl.project_code = p.code join {$prefix}member as m on tl.member_code = m.code where p.code = '{$projectCode}' and p.deleted = 0 order by tl.id desc";
            $list = Db::query($sql);
            $total = count($list);
            $sql .= " limit {$offset},{$pageSize}";
            $list = Db::query($sql);
            if ($list) {
                foreach ($list as &$item) {
                    $item['sourceInfo'] = [];
                    switch ($item['action_type']) {
                        case 'task':
                            $item['sourceInfo'] = \app\common\Model\Task::where(['code' => $item['source_code']])->find();
                            break;
                        case 'project':
                            $item['sourceInfo'] = \app\common\Model\Project::where(['code' => $item['source_code']])->find();
                            break;
                    }
                }
            }
            $list = ['total' => $total, 'list' => $list];
        }
        $this->success('', $list);
    }

    /**
     * 项目情况统计
     */
    public function _setDayilyProejctReport()
    {
        logRecord(nowTime(), 'setDayilyProejctReportBegin');
        debug('begin');
        $result = ProjectReport::setDayilyProejctReport();
        debug('end');
        logRecord(debug('begin', 'end') * 1000 . 'ms', 'setDayilyProejctReportSuccess');
        echo 'success_at ' . nowTime();
    }

    public function _getProjectReport()
    {
        $projectCode = Request::param('projectCode');
        if (!$projectCode) {
            $this->error('项目已失效');
        }
        $data = ProjectReport::getReportByDay($projectCode, 10);
        $this->success('', $data);
    }

    /**
     * 概览报表
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function _projectStats()
    {
        $projectCode = Request::param('projectCode');
        if (!$projectCode) {
            $this->error('项目已失效');
        }
        $project = \app\common\Model\Project::where(['code' => $projectCode])->find();
        if (!$project) {
            $this->error('项目已失效');
        }
        $taskStats = [
            'total' => 0,
            'unDone' => 0,
            'done' => 0,
            'overdue' => 0,
            'toBeAssign' => 0,
            'expireToday' => 0,
            'doneOverdue' => 0,
        ];
//        $taskList = \app\common\Model\Task::where(['project_code' => $projectCode, 'deleted' => 0])->field('id,assign_to,done,end_time,create_time,code')->hidden(['childCount,hasUnDone,parentDone,hasComment,hasSource,canRead'])->select()->toArray();
        $taskList = Db::name('task')->where(['project_code' => $projectCode, 'deleted' => 0])->field('id,assign_to,done,end_time,create_time,code')->select();
        $taskStats['total'] = count($taskList);
        if ($taskList) {
            $today = date('Y-m-d 00:00:00', time());
            $tomorrow = date('Y-m-d 00:00:00', strtotime($today) + 3600 * 24);
            foreach ($taskList as $item) {
                !$item['assign_to'] && $taskStats['toBeAssign']++;
                $item['done'] && $taskStats['done']++;
                !$item['done'] && $taskStats['unDone']++;
                if ($item['end_time']) {
                    if (!$item['done']) {
                        $item['end_time'] < nowTime() && $taskStats['overdue']++;
                        if ($item['end_time'] >= $today && $item['end_time'] < $tomorrow) {
                            $taskStats['doneOverdue']++;
                        }
                    } else {
                        $log = ProjectLog::where(['action_type' => 'task', 'source_code' => $item['code'], 'type' => 'done'])->order('id desc')->find();
                        if ($log && $log['create_time'] > $item['end_time']) {
                            $taskStats['doneOverdue']++;
                        }
                    }
                }
            }
        }

        $this->success('', $taskStats);
    }

    /**
     * 上传封面
     */
    public function uploadCover()
    {
        try {
            $file = $this->model->uploadCover(Request::file('cover'));
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        $this->success('', $file);
    }

    /**
     * 放入回收站
     */
    public function recycle()
    {
        try {
            $this->model->recycle(Request::post('projectCode'));
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        $this->success('');
    }

    /**
     * 恢复
     */
    public function recovery()
    {
        try {
            $this->model->recovery(Request::post('projectCode'));
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        $this->success('');
    }

    /**
     * 归档
     */
    public function archive()
    {
        try {
            $this->model->archive(Request::post('projectCode'));
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        $this->success('');
    }

    /**
     * 恢复归档
     */
    public function recoveryArchive()
    {
        try {
            $this->model->recoveryArchive(Request::post('projectCode'));
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        $this->success('');
    }

    /**
     * 退出项目
     */
    public function quit()
    {
        try {
            $this->model->quit(Request::post('projectCode'));
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        $this->success('');
    }

}
