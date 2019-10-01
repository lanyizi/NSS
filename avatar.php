<?php
require_once('Medoo-master/src/Medoo.php');
use Medoo\Medoo;

function main() {
    try {
        $timeStart = microtime(true); 

        $database = new Medoo([
            // required
            'database_type' => 'mysql',
            'database_name' => 'my_lanyi',
            'server' => 'localhost',
            'username' => 'lanyi',
            'password' => '',
            'charset' => 'utf8mb4',
	        'collation' => 'utf8mb4_general_ci',
            'option' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        ]);

        $maxSucceeded = $database->get('nss-avatar-fetch-history', 'count', [
            'timeStamp[>=]' => Medoo::raw('TIMESTAMP() - 86400'),
            'succeeded' => true,
            'ORDER' => [
                'timeStamp' => 'DESC'
            ]
        ]);

        $minFailed = $database->get('nss-avatar-fetch-history', 'count', [
            'timeStamp[>=]' => Medoo::raw('TIMESTAMP() - 86400'),
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

        $qqs = $database->select('nss-avatars', [
            '[<]nss-players' => 'qq'
        ], [
            'nss-avatars.qq',
        ], [
            'ORDER' => [
                'nss-avatars.lastUpdate' => 'ASC'
            ],
            'GROUP' => 'nss-avatars.qq',
            'HAVING' => [
                'OR' => [
                    'nss-avatars.lastUpdate[<]' => Medoo::raw('TIMESTAMP() - 10800'),
                    'nss-avatars.lastUpdate' => null
                ],
            ],
            'LIMIT' => $count
        ]);

        if(sizeof($qqs) == 0) {
            return 'Nothing to update';
        }

        $database->insert('nss-avatar-fetch-history', [
            'count' => $count,
            'succeeded' => false,
            'timeStamp' => Medoo::raw('TIMESTAMP()')
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
                $data = [
                    'lastUpdate' => Medoo::raw('TIMESTAMP()'),
                    'data' => curl_multi_getcontent($easyHandle)
                ];

                if(!$data['data']) {
                    continue;
                }
                $succeededCount += 1;

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

            $database->update('nss-avatar-fetch-history', [
                'succeeded' => true,
                'count' => $succeededCount
            ], [
                'id' => $historyId
            ]);

            $totalTime = microtime(true) - $timeStart;
            return "Successfully updated $succeededCount / $count items, total time = $totalTime";
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
        return $exception->getMessage();
    }
}

echo main();
?>