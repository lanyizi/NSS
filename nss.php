<?php
require_once('Medoo-master/src/Medoo.php');
require_once('auth.php');
require_once('util.php');

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

class NSSException extends Exception {
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

class NSS {
    /**
     * @var \Medoo\Medoo
     */
    private $database;

    private $input;

    /**
     * @var \Auth
     */
    private $auth;

    public function __construct($input, Medoo $database) {
        $this->input = $input;
        $this->database = $database;
        $this->auth = new Auth($this->database);
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
        return $this->auth->removeUser($this->input['token'], $this->input['username']);
    }

    public function getPlayers() {
        $access = $this->auth->verifyToken($this->input['token']);

        $keys = [
            'id',
            'name',
            'nickname',
            'level',
            'qq',
            'judgeDate',
            'judger',
            'faction',
            'description',
            'replays'
        ];

        if($access['accessLevel'] > 0) {
            array_splice($keys, 1, 0, ['qq']);
        }

        // 从数据库获取玩家信息
        $playerData = $this->database->select('nss-players', $keys, [
            'deletedDate' => null
        ]);

        foreach($playerData as $index => $player) {
            // 解析录像列表json，迭代时 $player 本身无法被赋值，
            // 只好通过 $index 重新访问数组元素
            $playerData[$index]['replays'] = json_decode($player['replays'], true);
        }

        return [
            'players' => $playerData
        ];
    }

    public function getPlayerHistory() {
        // 从数据库获取玩家信息
        $history = $this->database->select('nss-players', [
            'id',
            'name',
            'nickname',
            'level',
            'judgeDate',
            'judger',
            'faction',
            'description',
            'replays',
            'deletedBy',
            'deletedDate'
        ], [
            'qq' => $_GET['qq']
        ]);

        foreach($history as $index => $playerRecord) {
            // 解析录像列表json，迭代时 $player 本身无法被赋值，
            // 只好通过 $index 重新访问数组元素
            $history[$index]['replays'] = json_decode($playerRecord['replays'], true);
        }

        return [
            'players' => $history
        ];      
    }

    // “删除”玩家的信息
    private function disablePlayerRecord($qq, $judger, $additionalAction) {
        // 开始一次事务（transaction）
        $this->database->action(function(Medoo $database) use ($qq, $judger, $additionalAction) {
            $database->update('nss-players', [
                'deletedBy' => $judger,
                'deletedDate' => Medoo::raw('UNIX_TIMESTAMP()')
            ], [
                'AND' => [
                    'deletedDate' => null,
                    'qq' => $qq
                ]
            ]);

            // 假如有额外的东西要执行的话，就执行它
            if (is_callable($additionalAction)) {
                $additionalAction($database);
            }
        });
    }

    public function judgePlayer() {
        try {
            $access = $this->auth->verifyToken($this->input['token']);
            if(!($access['accessLevel'] > 0)) {
                return [
                    'result' => false,
                    'message' => "没有权限"
                ];
            }

            $qq = $this->input['qq'];
            $judger = $access['username'];
            $replays = getOrDefault($this->input['replays'], []);

            // 设置玩家
            $playerData = [
                'name' => $this->input['name'],
                'nickname' => getOrDefault($this->input['nickname']),
                'level' => $this->input['level'],
                'qq' => $this->input['qq'],
                'judger' => $judger,
                'judgeDate' => $this->input['judgeDate'],
                'faction' => $this->input['faction'],
                'description' => getOrDefault($this->input['description'], ''),
                //录像就直接用JSON字符串保存好了
                'replays' => json_encode($replays)
            ];

            // 新的玩家信息
            $addPlayerRecord = function(Medoo $database) use ($playerData) {
                $database->insert('nss-players', $playerData);
            };

            // 禁用之前的玩家信息，并加入新的信息
            $this->disablePlayerRecord($qq, $judger, $addPlayerRecord);
            
            return [
                'result' => true,
                'message' => "已保存玩家信息"
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

    public function removePlayer() {
        try {
            $access = $this->auth->verifyToken($this->input['token']);
            if(!($access['accessLevel'] > 0)) {
                return [
                    'result' => false,
                    'message' => "没有权限"
                ];
            }

            // 禁用玩家信息
            $this->disablePlayerRecord($this->input['qq'], $access['username'], null);
        
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

    public function removeReplay() {
        try {
            $this->database->action(function(Medoo $database) {
                $access = $this->auth->verifyToken($this->input['token']);
                if(!($access['accessLevel'] > 0)) {
                    throw new NSSException('没有权限');
                }

                $id = $this->input['id'];
                $result = $database->update('new-replays', [
                    'deletedBy' => $access['username'],
                    'deletedDate' => Medoo::raw('UNIX_TIMESTAMP()')
                ], [
                    'AND' => [
                        'deletedDate' => null,
                        'id' => $id
                    ]
                ]);

                $count = $result->rowCount();
                if($count !== 1) {
                    throw new NSSException("尝试删除录像时出错：找到了 $count 个录像");
                }
            });

            return [
                'result' => true,
                'message' => '删除成功'
            ];
        }
        catch(NSSException $exception) {
            return [
                'result' => false,
                'message' => $exception->getMessage()
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
}

echo json_encode(main());

?>