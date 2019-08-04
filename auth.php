<?php
require_once('util.php');

class Auth {

    private $database;

    public function __construct($database) {
        $this->database = $database;
    }

    // 登录
    public function login($username, $password) {
        // 默认 token
        $defaultToken = [
            'token' => '0'
        ];

        if(!isNullOrEmptyString($username) || !isNullOrEmptyString($password)) {
            return $defaultToken;
        }

        // 在数据库里查找当前用户名$username对应的密码
        $passHash = $this->database->get('nss-admins', 'passHash', [
            'username' => $username,
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
    
        if($inputToken == '0') {
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
            'username' => $username,
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

    public function setUser($token, $username, $password, $accessLevel, $description) {
        $noPrivilegeResult = [
            'result' => false,
            'message' => '没有权限'
        ];
    
        $admin = $this->verifyToken($token);
        $adminName = $admin['name'];
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
    
        $newData = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'accessLevel' => $accessLevel,
            'description' => $description,
            'updated-by' => $adminName,
            'updated-date' => time()
        ];
    
        if($this->database->has('nss-admins', [
            'username' => $username
        ])) {
            $this->database->replace('nss-admins', $newData, [
                'username' => $username
            ]);
        }
        else {
            $this->database->insert('nss-admins', $newData);
        }
    
        return [
            'result' => true,
            'message' => '操作成功'
        ];
    }

    // 根据密码生成一个 hash
    private function makeTokenHash($passhash, $hexSalt) {
        return hash_pbkdf2('sha256', "$passhash!NSSTOKEN", hex2Bin($hexSalt), 1234);
    }
}


?>