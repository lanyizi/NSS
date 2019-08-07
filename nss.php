<?php
require_once('Medoo-master/src/Medoo.php');
require_once('auth.php');
require_once('util.php');
require_once('replay.php');

use Medoo\Medoo;

function main() {
    try {
        // 表明返回的数据是 json
        header('Content-Type: application/json;charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
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

        $nss = new NSS($input, $database);
        // 执行请求
        return $nss->doAction($_GET['do']);
    }
    catch(Exception $exception) {
        http_response_code(500);
        return [
            'exception' => $exception->getMessage()
        ];
    }
}


class NSS {
    private $database;
    private $input;
    private $auth;
    private $replayDirectory;

    public function __construct($input, $database) {
        $this->input = $input;
        $this->database = $database;
        $this->auth = new Auth($this->database);
        $this->replayDirectory = 'uploadedReplays';

        // 假如不存在录像文件夹，那就创建一个
        if(!is_dir($this->replayDirectory)) {
            mkdir($this->replayDirectory, 0777, true);
        }
        
    }

    // 根据 what 的值，调用不同的方法
    public function doAction($what) {
        if(!method_exists($this, $what)) {
            http_response_code(400);
            return null;
        }

        $methodInfo = new ReflectionMethod($this, $what);
        if(!$methodInfo->isPublic()) {
            http_response_code(400);
            return null;
        }

        return $this->$what();
    }

    public function login() {
        $username = $this->input['username'];
        $password = $this->input['password'];
        return $this->auth->login($username, $password);
    }

    public function getAccessLevel() {
        $token = $_GET['token'];
        return $this->auth->verifyToken($token);
    }

    public function getJudgers() {
        $judgers = $this->database->select('nss-admins', [
            'username',
            'description'
        ]);
        return [
            'judgers' => $judgers
        ];
    }

    public function setJudger() {
        $adminToken = $this->input['token'];
        $username = $this->input['username'];
        $password = $this->input['password'];
        $accessLevel = $this->input['accessLevel'];
        $description = $this->input['description'];
        try {
            return $this->auth->setUser($adminToken, $username, $password, $accessLevel, $description);
        }
        catch(Exception $exception) {
            $errorMessage = $exception->getMessage();
            return [
                'result' => false,
                'message' => "遇到了内部错误：$errorMessage"
            ];
        }
    }

    public function removeJudger() {
        $this->auth->removeUser($this->input['token'], $this->input['username']);
    }

    public function getPlayers() {
        // 从数据库获取玩家信息
        $playerData = $this->database->select('nss-players', [
            'name',
            'nickname',
            'level',
            'qq',
            'judgeDate',
            'judger',
            'faction',
            'description',
            'replays'
        ]);

        foreach($playerData as $index => $player) {
            // 解析录像列表json，迭代时 $player 本身无法被赋值，
            // 只好通过 $index 重新访问数组元素
            $playerData[$index]['replays'] = json_decode($player['replays']);
        }

        return [
            'players' => $playerData
        ];
    }

    public function judgePlayer() {
        $access = $this->auth->verifyToken($this->input['token']);
        if(!($access['accessLevel'] > 0)) {
            return [
                'id' => null,
                'message' => '没有权限'
            ];
        }

        $replays = getOrDefault($this->input['replays'], []);

        $playerData = [
            'name' => $this->input['name'],
            'nickname' => getOrDefault($this->input['nickname']),
            'level' => $this->input['level'],
            'qq' => $this->input['qq'],
            'judger' => $access['username'],
            'judgeDate' => $this->input['judgeDate'],
            'faction' => $this->input['faction'],
            'description' => getOrDefault($this->input['description'], ''),
            //录像就直接用JSON字符串保存好了
            'replays' => json_encode($replays)
        ];

        // 把数据存入数据库
        try {
            $id = $this->database->get('nss-players', 'id', [
                'name' => $this->input['name']
            ]);

            if(isset($id)) {
                // 假如数据库里有这个玩家的信息,就更新旧的玩家信息
                $this->database->replace('nss-players', $playerData, [
                    'id' => $id
                ]);
            }
            else {
                // 否则就新增新的玩家信息
                $this->database->insert('nss-players', $playerData);
                $id = $this->database->id();
            }

            return [
                'id' => $id,
                'message' => '操作成功'
            ];
        }
        catch(Exception $exception) {
            $errorMessage = $exception->getMessage();
            return [
                'id' => null,
                'message' => "遇到了内部错误：$errorMessage"
            ];
        }
    }

    public function removePlayer() {
        $access = $this->auth->verifyToken($this->input['token']);
        if(!($access['accessLevel'] > 0)) {
            return [
                'result' => false,
                'message' => '没有权限'
            ];
        }

        try {
            // 删除玩家
            $result = $this->database->delete('nss-players', [
                'name' => $this->input['name']
            ]);

            if($result->rowCount() == 0) {
                return [
                    'result' => false,
                    'message' => '没有找到这个玩家'
                ];
            }
            
            return [
                'result' => true,
                'message' => '操作成功'
            ];
        }
        catch(Exception $exception) {
            $errorMessage = $exception->getMessage();
            return [
                'result' => false,
                'message' => "遇到了内部错误：$errorMessage"
            ];
        }
    }

    public function getReplayInformation() {
        $id = $_GET['id'];
        $replayInformation = $this->database->get('nss-replays', [
            'fileName',
            'fileSize',
            'mapName',
            'mapPath',
            'timeStamp',
            'players'
        ], [
            'id' => $id
        ]);

        if(empty($replayInformation)) {
            $replayInformation = null;
        }
        else {
            $replayInformation['players'] = json_decode($replayInformation['players']);
            $replayInformation['url'] = $this->getFinalReplayName($id);
        }

        return [
            'replay' => $replayInformation
        ];
    }

    public function uploadReplay() {
        $result = [
            'id' => null,
            'message' => '上传成功'
        ];
        // 开始一次事务（transaction）
        $this->database->action(function() use (&$result) {
            try {
                $replayFile = base64_decode($this->input['data']);
                // 解析录像信息
                $replayData = RA3Replay::parseRA3Replay($replayFile);
                $replayData['players'] = json_encode($replayData['players']); // 用JSON来存储玩家数组
                $replayData['fileName'] = $this->input['fileName'];

                // 把录像信息加到数据库里
                $this->database->insert('nss-replays', $replayData);
                $result['id'] = $this->database->id();
                // 保存录像文件
                $finalFileName = $this->getFinalReplayName($result['id']);
                $writeResult = file_put_contents($finalFileName, $replayFile);
                if(!$writeResult) {
                    $result['id'] = null;
                    $result['message'] = '保存录像文件失败';
                    return false; // 返回 false 会导致事务回滚，之前添加到数据库里的数据也会回滚
                }
            }
            catch(Exception $exception) {
                $errorMessage = $exception->getMessage();
                $result['id'] = null;
                $result['message'] = "遇到了内部错误：$errorMessage";
                return false;
            }
        });

        return $result;
    }

    public function removeReplay() {
        $access = $this->auth->verifyToken($this->input['token']);
        if(!($access['accessLevel'] > 0)) {
            return [
                'result' => false,
                'message' => '没有权限'
            ];
        }

        $result = [
            'result' => true,
            'message' => '删除成功'
        ];

        // 开始一次事务（transaction）
        $this->database->action(function() use (&$result) {
            try {
                $this->database->delete('nss-replays', [
                    'id' => $this->input['id']
                ]);
    
                $finalFileName = $this->getFinalReplayName($result['id']);
                $deleteResult = unlink($finalFileName);
                if(!$deleteResult) {
                    $result['result'] = false;
                    $result['message'] = "删除录像失败";
                    return false; // 返回 false 会导致事务回滚，之前对数据库造成的修改也会回滚
                }
            }
            catch(Exception $exception) {
                $errorMessage = $exception->getMessage();
                $result['result'] = false;
                $result['message'] = "遇到了内部错误：$errorMessage";
                return false;
            }
        });

        return $result;
    }

    private function getFinalReplayName($id) {
        return $this->replayDirectory . '/' . $id . '.RA3Replay';
    }

}

echo json_encode(main());

?>