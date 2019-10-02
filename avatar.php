<?php
require_once('Medoo-master/src/Medoo.php');
use Medoo\Medoo;

function updateAvatar($database, $qq, $avatarData) {
    $data = [
        'lastUpdate' => Medoo::raw('UNIX_TIMESTAMP()'),
        'data' => $avatarData
    ];

    if($database->has('nss-avatars', ['qq' => $qq])) {
        $database->update('nss-avatars', $data, [
            'qq' => $qq
        ]);
    }
    else {
        $data['qq'] = $qq;
        $database->insert('nss-avatars', $data);
    }
}

function autoUpdateAvatars($database) {
    try {
        $timeStart = microtime(true); 

        $maxSucceeded = $database->get('nss-avatar-fetch-history', 'count', [
            'timeStamp[>=]' => Medoo::raw('(UNIX_TIMESTAMP() - 86400)'),
            'succeeded' => true,
            'ORDER' => [
                'timeStamp' => 'DESC'
            ]
        ]);

        $minFailed = $database->get('nss-avatar-fetch-history', 'count', [
            'timeStamp[>=]' => Medoo::raw('(UNIX_TIMESTAMP() - 86400)'),
            'succeeded' => false,
            'ORDER' => [
                'timeStamp' => 'DESC'
            ]
        ]);

        if(empty($maxSucceeded)) {
            $maxSucceeded = 1;
        }

        if(empty($minFailed)) {
            $minFailed = $maxSucceeded * 4;
        }

        $count = min(max(0, $maxSucceeded * 2 - 1), max(0, $minFailed / 2 - 1)) + 1;

        $qqs = $database->query("
            SELECT `qq` from \"nss-avatars\" RIGHT JOIN \"nss-players\" USING(`qq`)
            WHERE (\"nss-players\".`deletedDate` IS NULL AND (\"nss-avatars\".`lastUpdate` IS NULL OR \"nss-avatars\".`lastUpdate` < (UNIX_TIMESTAMP() - 7200)))
            ORDER BY \"nss-avatars\".`lastUpdate` ASC 
            LIMIT $count
        ")->fetchAll(PDO::FETCH_ASSOC);

        if(sizeof($qqs) == 0) {
            return 'Nothing to update';
        }

        $database->insert('nss-avatar-fetch-history', [
            'count' => $count,
            'succeeded' => false,
            'timeStamp' => Medoo::raw('UNIX_TIMESTAMP()')
        ]);
        $historyId = $database->id();

        try {
            $easyHandles = [];
            $multiHandle = curl_multi_init();

            foreach($qqs as $items) {
                $qq = $items['qq'];
                $easyHandles[$qq] = curl_init("https://ptlogin2.qq.com/getface?appid=1006102&imgtype=3&uin=$qq");
                curl_setopt($easyHandles[$qq], CURLOPT_HEADER, 0);
                curl_setopt($easyHandles[$qq], CURLOPT_RETURNTRANSFER, true);
                curl_multi_add_handle($multiHandle, $easyHandles[$qq]);
            }

            $running = null;
            do {
                curl_multi_exec($multiHandle, $running);
                while(curl_multi_select($multiHandle, 0.1) == -1) {
                    usleep(100);
                    if((microtime(true) - $timeStart) > 4) {
                        throw new Exception('Timeout!');
                    }
                }
            }
            while ($running > 0);

            $succeededCount = 0;
            foreach($easyHandles as $qq => $easyHandle) {
                $data = curl_multi_getcontent($easyHandle);

                if(!$data) {
                    continue;
                }
                $succeededCount += 1;

                updateAvatar($database, $qq, $data);
            }

            if($succeededCount == 0) {
                throw new Exception('Nothing succeeded');
            }

            $database->update('nss-avatar-fetch-history', [
                'succeeded' => true,
                'count' => $succeededCount
            ], [
                'id' => $historyId
            ]);

            $totalTime = microtime(true) - $timeStart;
            $size = sizeof($items);
            return "Successfully updated $succeededCount / $size items, total time = $totalTime";
        }
        finally {
            foreach($easyHandles as $qq => $easyHandle) {
                curl_multi_remove_handle($multiHandle, $easyHandle);
                curl_close($easyHandle);
            }
            curl_multi_close($multiHandle);
        }
    }
    catch(Exception $exception) {
        http_response_code(500);
        $message = $exception->getMessage();
        return "Exception: $message";
    }
}
?>