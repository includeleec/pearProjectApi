# Docker 部署使用说明

包含:
- Nginx, port:80
- php:7.2-fpm, port:9000
- mysql, port:3306
- adminer(mysql admin web UI), port:8080
- redis, port:6379



```
# First use
docker-compose build

# How to use
docker-compose up
```


## Test Mysql connect
```
$servername = 'mysql';
$username = 'root';
$password = 'root!';

try {
    $conn = new PDO("mysql:host=$servername;", $username, $password);
    echo "连接成功"; 
}
catch(PDOException $e)
{
    echo $e->getMessage();
}
```

### Test Rredis connect
```
$redis = new Redis();
$redis->connect('redis', 6379);
echo "Connection to server sucessfully<br/>";
$redis->set("tutorial-name", "leec");
echo "Stored string in redis:: " . $redis->get("tutorial-name");
```

# Try open
http://localhost/test/test_mysql.php
http://localhost/test/test_redis.php

# 配置 ENV
注意查看根目录的 .env 文件中, mysql, redis 的 host 参数为 docker-compose 中对应的 key
```
cp .env.example .env
```

# 查看环境是否都准备好
http://127.0.0.1/
返回以下结果, 表示部署成功
```
{
code: 200,
msg: "后端部署成功",
data: [ ]
}
```

# 如果是第一次部署， msyql 为全新
http://127.0.0.1/index.html#/install


# 安装指南
https://www.yuque.com/bzsxmz/siuq1w/kggzna

# 安装 php 依赖

```
中国镜像加速：
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

composer install

# 忽略本地php配置，因为我们是在docker 环境执行
composer install --ignore-platform-reqs 
```

# 初次登陆
默认账号密码 123456/123456
也可以自己创建一个新的账户，邮箱/短信验证码都直接在前端弹出显示