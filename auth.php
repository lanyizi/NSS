<?php
require_once('salts.php');

function isNullOrEmptyString($str){
    return (!isset($str) || trim($str) === '');
}

function makeToken($database, $username, $password) {
    if(!isNullOrEmptyString($username) || !isNullOrEmptyString($password)) {
        return '0';
    }

    $userdata = $database->select('nss-admins', [
        'passhash'
    ], [
        'name' => $username,
    ]);

    if(sizeof($userdata) == 0) {
        return '0';
    }

    $passhash = $userdata[0]['passhash'];

    if(!password_verify($password, $passhash)) {
        return '0';
    }

    $salt = openssl_random_pseudo_bytes(16);
    $tokenpass = hash_pbkdf2('sha256', "$passhash!NSSTOKEN", $salt, 1234);
    $hexSalt = bin2hex($salt);

    return "$hexSalt|$tokenpass|$username";
}

function verifyToken($database, $token) {
    $default = [
        'name' => '游客',
        'accessLevel' => 0
    ];

    if($token == '0') {
        return $default;
    }

    $splitted = explode('|', $token, 3);
    if(sizeof($splitted) != 3) {
        return $default;
    }

    $salt = hex2bin($splitted[0]);
    $tokenpass = $splitted[1];
    $username = $splitted[2];

    $userdata = $database->select('nss-admins', [
        'passhash', 'accesslevel'
    ], [
        'name' => $username,
    ]);

    if(sizeof($userdata) == 0) {
        return $default;
    }

    $passhash = $userdata[0]['passhash'];
    $accessLevel = $userdata[0]['accesslevel'];
    $calculated = hash_pbkdf2('sha256', "$passhash!NSSTOKEN", $salt, 1234);
    if(!hash_equals($tokenpass, $calculated)) {
        return $default;
    }

    return [
        'name' => $username,
        'accessLevel' => $accessLevel
    ];
}

function setUser($database, $token, $username, $password, $accessLevel, $description) {
    $noPrivilegeResult = [
        'result' => false,
        'message' => '没有权限'
    ];

    $admin = verifyToken($database, $token);
    $adminName = $admin['name'];
    $adminLevel = $admin['accessLevel'];
    if($adminLevel == 0) {
        return $noPrivilegeResult;
    }

    if(($adminName != $username) && ($adminLevel < 2)) {
        return $noPrivilegeResult;
    }

    if(($adminName == $username) && ($accessLevel != $adminLevel)) {
        return $noPrivilegeResult;
    }

    $existing = $database->select('nss-admins', [
        'name'
    ], [
        'name' => $username
    ]);

    $newData = [
        'name' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'accessLevel' => $accessLevel,
        'description' => $description,
        'updated-by' => $adminName,
        'updated-date' => time()
    ];

    if(sizeof($existing) == 0) {
        $database->insert('nss-admins', $newData);
    }
    else {
        $database->replace('nss-admins', $newData, [
            'name' => $username
        ]);
    }

    return [
        'result' => true,
        'message' => '操作成功'
    ];
}

?>