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


class NSS {
    private $database;
    private $input;
    private $auth;

    public function __construct($input, $database) {
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

    public function getPlayers() {
        // 从数据库获取玩家信息
        $playerData = $this->database->select('nss-players', [
            'id',
            'name',
            'level',
            'qq',
            'judgeDate',
            'judger',
            'faction',
            'description'
        ]);

        foreach($playerData as $index => $player) {
            // 从数据库获取与玩家关联的录像ID，并把它添加到玩家信息里
            $playerData[$index]['replays'] = $this->database->select('nss-replays-players', [
                'replay'
            ], [
                'player' => $player['id']
            ]);
        }

        return [
            'players' => $playerData
        ];
    }

    public function judgePlayer() {
        $access = $this->auth->verifyToken($this->input['token']);
        if(!($access['accessLevel'] > 0)) {
            return [
                'result' => false,
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
            'judgeDate' => $this->input['judgedate'],
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
                'result' => true,
                'message' => '操作成功'
            ];
        }
        catch(Exception $exception) {
            $errorMessage = $exception->getMessage();
            return [
                'result' => true,
                'message' => "内部错误：$errorMessage"
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
            $this->database->delete('nss-players', [
                'name' => $this->input['name']
            ]);

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

    /*public function uploadReplay() {
        
        $replayFile = base64_decode($this->input['data']);
        $replayMagic = 'RA3 REPLAY HEADER';
        if(substr($replayFile, 0, strlen($replayMagic)) != $replayMagic) {
            return [
                'result' => false,
                'message' => '不是红警3录像文件'
            ];
        }

        $this->database->action(function() {
            $replayFile = base64_decode($this->input['data']);

            
            

            if($replayFile)

            $replayData = [
                'fileName' => $this->input['fileName']
            ];
            $writeResult = file_put_contents();
        });

        
    }*/

}

echo json_encode(main());

?>