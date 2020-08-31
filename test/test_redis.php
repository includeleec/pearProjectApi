<?php
    $redis = new Redis();
    $redis->connect('redis', 6379);
    echo "Connection to server sucessfully<br/>";
    $redis->set("tutorial-name", "leec");
    echo "Stored string in redis:: " . $redis->get("tutorial-name");