<?php

function isNullOrEmptyString($str){
    return (!isset($str) || trim($str) === '');
}

function getOrDefault(&$var, $default = null) {
    return isset($var) ? $var : $default;
}

?>