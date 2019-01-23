<?phpnamespace App\Domain\Team;use function App\addLog;use App\Auth\Domain\User;use App\Common\CommonDomain;use App\Common\Exception\WrongRequestException;use function App\nowTime;/** * 团队类 * * @author lws */class Team extends CommonDomain{    private static $Model = null;    public function __construct()    {        if (self::$Model == null) {            self::$Model = new \App\Model\Team\Team();        }    }    public function editLeader($team_id,$user_id,$state = 1)    {        $model_user = new \App\Model\User\User();        $user_info = $model_user->get($user_id);        $team_info = self::$Model->get($team_id);        if (!$user_info) {            throw new WrongRequestException('该成员不存在', 1);        }        if (!$team_info) {            throw new WrongRequestException('该团队不存在', 2);        }        if ($state == 1) {            if ($team_info and $team_info['leader_id']) {                if ($team_info['leader_id'] != $user_id) {                    throw new WrongRequestException('该团队已有负责人', 3);                }else{                    throw new WrongRequestException('该成员已是负责人', 4);                }            }else{                $result = self::$Model->update($team_id,array('leader_id'=>$user_id));                if ($result) {                    addLog("设置 {$team_info['team_name']} 负责人为 {$user_info['nick_name']}");                }            }        }else{            $leader_info = $model_user->get($team_info['leader_id']);            $result = self::$Model->update($team_id,array('leader_id'=>0));            if ($result) {                addLog("取消 {$team_info['team_name']} {$leader_info['nick_name']} 负责人身份");            }        }    }    public function getNoInTeamUser($param)    {        if (!is_array($param)) {            $param = get_object_vars($param);        }        $offset = ($param['page_num'] - 1) * $param['page_size'];        $order = $param['order'] == '' ? '' : ' ORDER BY ' . $param['order'];        $key_word = $param['keyWord'] == '' ? ' WHERE 1=1 ' :  " WHERE ( u.account like '%".$param['keyWord']."%' OR u.realname like '%".$param['keyWord']."%') ";        $prefix = \PhalApi\DI()->config->get('dbs.tables.__default__.prefix');        $sql = 'SELECT *,u.id as u_user_id '//            . 'FROM ' . $prefix . 'team AS t '            . 'FROM ' . $prefix . 'team_user AS tu '            . 'RIGHT JOIN ' . $prefix . 'user AS u '            . ' ON tu.user_id = u.id'            . $key_word            . 'AND u.deleted = "0" '//            . 'AND (tu.team_id != :id  or tu.team_id is null) group by u.id ';            . ' group by u.id ';        $params = array(':id' => $param['team_id']);        $lists = \PhalApi\DI()->notorm->notTable->queryRows($sql, $params);        $count = count($lists);        $sql .= $order.' LIMIT ' . $offset . ',' . $param['page_size'];        $lists = \PhalApi\DI()->notorm->notTable->queryRows($sql, $params);        if ($lists) {            $model_team_user = new \App\Model\Team\TeamUser();            foreach ($lists as $key=>&$item){                $item['is_add'] = false;                $res = $model_team_user->getInfo(array('team_id'=>$param['team_id'],'user_id'=>$item['u_user_id']),'id');                if ($res) {                    unset($lists[$key]);                    $count--;                }            }            unset($item);        }        $list = array('list' => $lists, 'count' => $count);        return $list;    }    public function getUserTeam($user_id)    {        $model_team_user = new \App\Model\Team\TeamUser();        return $model_team_user->getListByWhere(array('user_id'=>$user_id));    }    public function getList($param)    {        if (!is_array($param)) {            $param = get_object_vars($param);        }        if (isset($param['keyWord'])) {            $param['where']["team_name LIKE ? or state = ? "] = array("%" . $param['keyWord'] . "%", $param['keyWord']);        }        if ($param['pid'] != -1) {            $param['where']['pid'] = $param['pid'];        }        $list = self::$Model->getList($param);        if ($list['list']) {            $domain_user = new User();            foreach ($list['list'] as &$item) {                $item['leader_info'] = array();                if ($item['leader_id']) {                    $item['leader_info'] = $domain_user->getUserInfo($item['leader_id']);                }                if ($item['pid']) {                    $item['parent_info'] = $this->getInfo(array('id' => $item['pid']));                } else {                    $item['parent_info'] = array();                }            }            unset($item);        }        return $list;    }    public function getInfo($where, $field = '*')    {        $team_info = self::$Model->getInfo($where, $field);        if ($team_info) {            $domain_user = new \App\Domain\User\User();            $leader_info = array();            if ($team_info['leader_id']) {                $leader_info =  $domain_user->getUserById($team_info['leader_id']);            }            $team_info['leader_info'] = $leader_info;        }        return $team_info;    }    public function changeState($team_id, $state)    {        $result = self::$Model->update($team_id, array('state' => $state));        if ($result === false) {            throw new WrongRequestException('操作失败', 1);        }        addLog("修改团队状态，编号：[$team_id]，状态：[$state]");    }    public function getTeamPath($team_id,$delimiter = false)    {        $path_array = array();        $team_info = self::$Model->get($team_id,'team_name,pid,id');        while ($team_info) {            $path_array[] = $team_info['team_name'];            $pid = $team_info['pid'];            $team_info = self::$Model->get($pid,'team_name,pid,id');        }        $path_array = array_reverse($path_array);        if ($delimiter) {            $path = '';            foreach ($path_array as $key=>$team_name) {                if ($key < count($path_array) - 1) {                    $path .= "{$team_name}".$delimiter;                }else{                    $path .= "{$team_name}";                }            }            return $path;        }        return $path_array;    }    /** 删除团队     * @param $ids id列表 如1,2,3     * @return int     */    public function delTeam($ids)    {        $result = self::$Model->delItems($ids);        if ($result) {            $model_team_user = new \App\Model\Team\TeamUser();            foreach ($ids as $id) {                $model_team_user->delTeam($id);            }            $ids = json_encode($ids);            addLog("删除团队，编号：$ids");        }        return $result == true ? 0 : 1;    }    /**     *  新增团队     * @param $data     * @throws WrongRequestException     */    public function addTeam($data)    {        $data['create_time'] = nowTime();        $id = self::$Model->insert($data);        if ($id === false) {            throw new WrongRequestException('新增失败', 8);        }        addLog('新增团队，编号：' . $id);    }    public function editTeam($id, $data)    {        if ($data['pid'] == $id) {            throw new WrongRequestException('隶属团队不能为自身', 1);        }        $result = self::$Model->update($id, $data);        if ($result === false) {            throw new WrongRequestException('保存失败', 6);        }        addLog('修改团队，团队ID：' . $id);    }    public function getNextTeam($team_id)    {        $temp_list = $this->getItem(array('id'=>$team_id),array('id'=>$team_id));        $list = array();        if ($temp_list) {            foreach ($temp_list as $item) {                $team_info = $this->getInfo(array('id'=>$item));                $list[] = $team_info;            }        }        return $list;    }    public function getItem($ids, $list){        $new_ids = self::$Model->getListByIds($ids,'pid','id desc','id');        if ($new_ids) {            $list = array_merge($list, $new_ids);            return $this->getItem($new_ids,$list);        }else{            return $list;        }    }}