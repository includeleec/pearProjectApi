<?php

namespace app\common\Model;

use service\FileService;
use think\Db;
use think\facade\Hook;
use think\File as thinkFile;

/**
 * 项目
 * Class Organization
 * @package app\common\Model
 */
class Project extends CommonModel
{
    protected $append = [];
    protected $defaultStages = [['name' => '待处理'], ['name' => '进行中'], ['name' => '已完成']];

    // 当前任务阶段
    public function currentTaskStage() {
        return $this->belongsTo('taskStages','current_task_stage_id');
    }

    // 负责人
    public function belongMember() {
        return $this->belongsTo('Member','belong_member_id');
    }

    // 负责部门
    public function belongDepartment() {
        return $this->belongsTo('Department','belong_department_id');
    }

    // 项目合同
    public function contract() {
        return $this->belongsTo('Contract');
    }

    public static function getEffectInfo($id)
    {
        return self::where(['id' => $id, 'deleted' => 0, 'archive' => 0])->find();
    }

    public function getProjects( $deleted = 0, $archive = 0, $collection = -1, $page = 1, $pageSize = 10)
    {
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $pageSize;
        $limit = $pageSize;
        $prefix = config('database.prefix');
        $sql = "select *,p.id as id,p.name as name,p.code as code,p.create_time as create_time 
                from {$prefix}project as p join {$prefix}project_member as pm on p.code = pm.project_code left join {$prefix}project_collection as pc 
                on p.code = pc.project_code";
        if ($deleted != -1) {
            $sql .= " and p.deleted = {$deleted} ";
        }
        if ($archive != -1) {
            $sql .= " and p.archive = {$archive} ";
        }
        if ($collection == 1) {
            $sql .= " and pc.project_code is not null";
        }
        $sql .= "group by p.id order by pc.id desc, p.id desc";
        $total = Db::query($sql);
        $total = count($total);
        $sql .= " limit {$offset},{$limit}";
        $list = Db::query($sql);
        return ['list' => $list, 'total' => $total];
    }

    public function getMemberProjects($memberCode = '', $organizationCode = '', $deleted = 0, $archive = 0, $collection = -1, $page = 1, $pageSize = 10)
    {
        if (!$memberCode) {
            $memberCode = getCurrentMember()['code'];
        }
//        if (!$organizationCode) {
//            $organizationCode = getCurrentOrganizationCode();
//        }
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $pageSize;
        $limit = $pageSize;
        $prefix = config('database.prefix');
        $sql = "select *,p.id as id,p.name as name,p.code as code,p.create_time as create_time 
                from {$prefix}project as p join {$prefix}project_member as pm on p.code = pm.project_code left join {$prefix}project_collection as pc 
                on p.code = pc.project_code where pm.member_code = '{$memberCode}'";
        if ($deleted != -1) {
            $sql .= " and p.deleted = {$deleted} ";
        }
        if ($archive != -1) {
            $sql .= " and p.archive = {$archive} ";
        }
        if ($collection == 1) {
            $sql .= " and pc.project_code is not null and pc.member_code = '{$memberCode}'";
        }
        $sql .= "group by p.id order by pc.id desc, p.id desc";
        $total = Db::query($sql);
        $total = count($total);
        $sql .= " limit {$offset},{$limit}";
        $list = Db::query($sql);
        return ['list' => $list, 'total' => $total];
    }

    /**
     * 创建项目
     * @param $memberCode
     * @param $name
     * @param string $description
     * @param string $templateCode
     * @return Project
     * @throws \Exception
     */
    public function createProject($memberCode, $name, $description = '', $templateCode = '')
    {
        //d85f1bvwpml2nhxe94zu7tyi
        Db::startTrans();
        try {
            $project = [
                'create_time' => nowTime(),
                'code' => createUniqueCode('project'),
                'name' => $name,
                'description' => $description,
                'task_board_theme' => 'simple',
                'cover' => FileService::getFilePrefix() . 'static/image/default/project-cover.png',
            ];
            $result = self::create($project);
            $projectMemberModel = new ProjectMember();
            $projectMemberModel->inviteMember($memberCode, $project['code'], 1);
            if ($templateCode) {
                $stages = TaskStagesTemplate::where(['project_template_code' => $templateCode])->order('sort desc,id asc')->select();
            } else {
                $stages = $this->defaultStages;
            }
            if ($stages) {
                foreach ($stages as $key => $stage) {
                    $taskStage = [
                        'project_id' => $result['id'],
                        'project_code' => $project['code'],
                        'name' => $stage['name'],
                        'sort' => $key,
                        'code' => createUniqueCode('taskStages'),
                        'create_time' => nowTime(),
                    ];
                    $stagesResult = TaskStages::create($taskStage);
                    $taskStage['id'] = $stagesResult['id'];
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw new \Exception($e->getMessage(), 1);
        }
        self::projectHook(getCurrentMember()['code'], $project['code'], 'create');
        return $result;
    }

    /**
     * 创建项目
     */
    public function createProjectFromAPI($member, $data)
    {
        Db::startTrans();
        try {

            $project = [
                'create_time' => nowTime(),
                'code' => createUniqueCode('project'),
                'name' => $data['name'],
                'belong_member_id' => $member['id'],
                'belong_department_id' => $member['department_id'],
                'description' => '',
                'task_board_theme' => 'simple',
                'set_up_year' => $data['set_up_year'],
                'apply_set_up_date' => returnValidDatetime($data['apply_set_up_date']),
                'annual_assignment_date' => $data['annual_assignment_date'],
                'annual_assignment_batch' => $data['annual_assignment_batch'],
                'bidding_plan_submission_date'=> $data['bidding_plan_submission_date'],
                'bidding_no' => $data['bidding_no'],
                'bidding_batch' => $data['bidding_batch'],
                'bidding_amount' => $data['bidding_amount'],
                'bidding_evaluation_date' => $data['bidding_evaluation_date'],
                'winning_bid_accept_date' => $data['winning_bid_accept_date'],
                'winning_bid_name' => $data['winning_bid_name']

            ];


            $result = self::create($project);

            $contract = [
                'name' => $data['contract_name'],
                'code' => $data['contract_code'],
                'assign_date' => $data['contract_assign_date'],
                'performance_start_date' => $data['contract_performance_start_date'],
                'performance_end_date' => $data['contract_performance_end_date'],
                'amount' => $data['contract_amount'],
                'warranty_amount' => $data['contract_warranty_amount'],
                'warranty_date' => $data['contract_warranty_date'],
                'contact_name' => $data['contract_contact_name'],
                'contact_mobile' => $data['contract_contact_mobile'],
                'project_id' => $result['id']

            ];

            Contract::create($contract);


            $projectMemberModel = new ProjectMember();
            $projectMemberModel->inviteMember($member['code'], $project['code'], 1);
            if ($data['project_template_code']) {
                $stages = TaskStagesTemplate::where(['project_template_code' => $data['project_template_code']])->order('sort desc,id asc')->select();

                if ($stages) {
                    foreach ($stages as $key => $stage) {
                        // 初始化 计划时间，实际执行时间
                        $plan_date = null;
                        $execute_date = null;
                        foreach ($data['task_stages'] as $ts) {

                            // 查找到匹配的阶段名称
                            if($ts['name'] == $stage['name']) {
                                $plan_date = returnValidDatetime($ts['plan_date']);
                                $execute_date = returnValidDatetime($ts['execute_date']);
                            }
                        }

                        $taskStage = [
                            'project_id' => $result['id'],
                            'project_code' => $project['code'],
                            'name' => $stage['name'],
                            'sort' => $key,
                            'code' => createUniqueCode('taskStages'),
                            'create_time' => nowTime(),
                            'plan_date'=> $plan_date,
                            'execute_date' => $execute_date

                        ];
                        $stagesResult = TaskStages::create($taskStage);
                        $taskStage['id'] = $stagesResult['id'];
                    }
                }
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw new \Exception($e->getMessage(), 1);
        }
        self::projectHook(getCurrentMember()['code'], $project['code'], 'create');
        return $result;
    }

    public function edit($code, $data)
    {
        if (!$code) {
            throw new \Exception('请选择项目', 1);
        }
        $project = self::where(['code' => $code, 'deleted' => 0])->field('id', true)->find();
        if (!$project) {
            throw new \Exception('该项目在回收站中无法编辑', 1);
        }

        //添加 项目负责人时候校验
        if (isset($data['belong_member_code'])) {
            $member = Member::where(['code' => $data['belong_member_code']])->find();

            if (!$member) {
                throw new \Exception('belong_member_code 没有对应的 member', 2);
            }
        }

        //添加 负责部门
        if (isset($data['belong_dep_code'])) {
            $dep = Department::where(['code' => $data['belong_dep_code']])->find();

            if (!$dep) {
                throw new \Exception('belong_dep_code 没有对应的 department', 2);
            }
        }

        $result = self::update($data, ['code' => $code]);
        //TODO 项目动态
        self::projectHook(getCurrentMember()['code'], $code, 'edit');
        return $result;
    }

    /**
     * @param File $file
     * @return array|bool
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @throws \Exception
     */
    public function uploadCover(thinkFile $file)
    {
        return $this->_uploadImg($file);
    }

    /**
     * 放入回收站
     * @param $code
     * @return Project
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function recycle($code)
    {
        $info = self::where(['code' => $code])->find();
        if (!$info) {
            throw new \Exception('项目不存在', 1);
        }
        if ($info['deleted']) {
            throw new \Exception('项目已在回收站', 2);
        }
        $result = self::update(['deleted' => 1, 'deleted_time' => nowTime()], ['code' => $code]);
        self::projectHook(getCurrentMember()['code'], $code, 'recycle');
        return $result;
    }

    /**
     * 恢复项目
     * @param $code
     * @return Project
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function recovery($code)
    {
        $info = self::where(['code' => $code])->find();
        if (!$info) {
            throw new \Exception('项目不存在', 1);
        }
        if (!$info['deleted']) {
            throw new \Exception('项目已恢复', 2);
        }
        $result = self::update(['deleted' => 0], ['code' => $code]);
        self::projectHook(getCurrentMember()['code'], $code, 'recovery');
        return $result;
    }

    /**
     * 项目归档
     * @param $code
     * @return Project
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function archive($code)
    {
        $info = self::where(['code' => $code])->find();
        if (!$info) {
            throw new \Exception('项目不存在', 1);
        }
        if ($info['archive']) {
            throw new \Exception('项目已归档', 2);
        }
        $result = self::update(['archive' => 1, 'archive_time' => nowTime()], ['code' => $code]);
        self::projectHook(getCurrentMember()['code'], $code, 'archive');
        return $result;
    }

    /**
     * 恢复项目
     * @param $code
     * @return Project
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function recoveryArchive($code)
    {
        $info = self::where(['code' => $code])->find();
        if (!$info) {
            throw new \Exception('项目不存在', 1);
        }
        if (!$info['archive']) {
            throw new \Exception('项目已恢复', 2);
        }
        $result = self::update(['archive' => 0], ['code' => $code]);
        self::projectHook(getCurrentMember()['code'], $code, 'recoveryArchive');
        return $result;
    }

    /**
     * 退出项目
     * @param $code
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \Exception
     */
    public function quit($code)
    {
        $info = self::where(['code' => $code])->find();
        if (!$info) {
            throw new \Exception('项目不存在', 1);
        }
        $where = ['project_code' => $code, 'member_code' => getCurrentMember()['code']];
        $projectMember = ProjectMember::where($where)->find();
        if (!$projectMember) {
            throw new \Exception('你不是该项目成员', 2);
        }
        if ($projectMember['is_owner']) {
            throw new \Exception('创建者不能退出项目', 3);
        }
        $result = ProjectMember::where($where)->delete();
        return $result;
    }

    /** 项目变动钩子
     * @param $memberCode
     * @param $sourceCode
     * @param string $type
     * @param string $toMemberCode
     * @param int $isComment
     * @param string $remark
     * @param string $content
     * @param string $fileCode
     * @param array $data
     * @param string $tag
     */
    public static function projectHook($memberCode, $sourceCode, $type = 'create', $toMemberCode = '', $isComment = 0, $remark = '', $content = '', $fileCode = '', $data = [], $tag = 'project')
    {
        $data = ['memberCode' => $memberCode, 'sourceCode' => $sourceCode, 'remark' => $remark, 'type' => $type, 'content' => $content, 'isComment' => $isComment, 'toMemberCode' => $toMemberCode, 'fileCode' => $fileCode, 'data' => $data, 'tag' => $tag];
        Hook::listen($tag, $data);

    }
}
