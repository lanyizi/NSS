<?php

use Medoo\Medoo;

require_once('util.php');

class Auth {

    private $database;

    public function __construct(Medoo $database) {
        $this->database = $database;
    }

    // 登录
    public function login($username, $password) {
        // 默认 token
        $defaultToken = [
            'token' => '0'
        ];

        if(isNullOrEmptyString($username) || isNullOrEmptyString($password)) {
            return $defaultToken;
        }

        // 在数据库里查找当前用户名$username对应的密码
        $passHash = $this->database->get('nss-admins', 'passHash', [
            'AND' => [
                'deletedDate' => null,
                'username' => $username
            ]
        ]);

        // 假如找不到用户，那么返回默认 token
        if(!isset($passHash)) {
            return $defaultToken;
        }

        // 假设密码错误，那么返回默认 token
        if(!password_verify($password, $passHash)) {
            return $defaultToken;
        }

        // 生成一个 token
        $hexSalt = bin2hex(openssl_random_pseudo_bytes(16));
        $tokenHash = $this->makeTokenHash($passHash, $hexSalt);

        return [
            'token' => "$hexSalt|$tokenHash|$username"
        ];
    }

    public function verifyToken($inputToken) {
        // 默认身份
        $default = [
            'username' => '游客',
            'accessLevel' => 0
        ];

        if(empty($inputToken)) {
            return $default;
        }
    
        // 重新解析 token，把它分成3部分，salt，hash，username
        $splitted = explode('|', $inputToken, 3);
        if(sizeof($splitted) != 3) {
            return $default;
        }
    
        $hexSalt = $splitted[0];
        $tokenHash = $splitted[1];
        $username = $splitted[2];

        $userdata = $this->database->get('nss-admins', [
            'passhash', 'accesslevel'
        ], [
            'AND' => [
                'deletedDate' => null,
                'username' => $username
            ]
        ]);
    
        // 假如找不到用户，那么返回默认 token
        if(!isset($userdata)) {
            return $default;
        }
    
        $passHash = $userdata['passhash'];
        $accessLevel = $userdata['accesslevel'];
        // 计算校验
        $realHash = $this->makeTokenHash($passHash, $hexSalt);
        // 假设校验失败，那么返回默认 token
        if(!hash_equals($realHash, $tokenHash)) {
            return $default;
        }
    
        return [
            'username' => $username,
            'accessLevel' => $accessLevel
        ];
    }

    private function disableUser($targetUser, $disabledBy, $additionalAction) {
        $action = function (Medoo $database) use ($targetUser, $disabledBy, $additionalAction) {
            // 开始一次事务（transaction）
            $database->update('nss-admins', [
                'deletedBy' => $disabledBy,
                '#deletedDate' => 'CURRENT_TIMESTAMP'
            ], [
                'AND' => [
                    'deletedDate' => null,
                    'username' => $targetUser
                ]
            ]);

            // 假如有额外的东西要执行的话，就执行它
            if (is_callable($additionalAction)) {
                $additionalAction($database);
            }
        };
        $this->database->action($action);
    }

    public function setUser($token, $username, $password, $accessLevel, $description) {
        $noPrivilegeResult = [
            'result' => false,
            'message' => '没有权限'
        ];
    
        $admin = $this->verifyToken($token);
        $adminName = $admin['username'];
        $adminLevel = $admin['accessLevel'];
        if(!($adminLevel > 0)) {
            return $noPrivilegeResult;
        }
    
        if(($adminName != $username) && ($adminLevel < 2)) {
            return $noPrivilegeResult;
        }
    
        if(($adminName == $username) && ($accessLevel != $adminLevel)) {
            return $noPrivilegeResult;
        }

        try {
            $newData = [
                'username' => $username,
                'passhash' => password_hash($password, PASSWORD_DEFAULT),
                'accessLevel' => $accessLevel,
                'description' => $description,
                'updated-by' => $adminName,
                'updated-date' => time()
            ];
     
            $insertNewData = function ($database) use ($newData) {
                $database->insert('nss-admins', $newData);
            };

            // 禁用以前的用户，并加入新的信息
            $this->disableUser($username, $adminName, $insertNewData);

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

    public function removeUser($token, $username) {
        $access = $this->verifyToken($token);
        if(!($access['accessLevel'] > 1)) {
            return [
                'result' => false,
                'message' => '没有权限'
            ];
        }

        try {
            // 删除鉴定员
            $this->disableUser($username, $access['username'], null);

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

    // 根据密码生成一个 hash
    private function makeTokenHash($passhash, $hexSalt) {
        return hash_pbkdf2('sha256', "$passhash!NSSTOKEN", hex2Bin($hexSalt), 1234);
    }
}


?>