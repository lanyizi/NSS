<?php

class RA3Replay {
    // 返回一些比较重要的数据：
    // [
    //     'fileSize' => 录像文件大小,
    //     'mapName' => '地图名称',
    //     'mapPath' => '地图路径',
    //     'timeStamp' => '录像时间戳',
    //     'players' => [ '玩家列表' ]
    // ]
    public static function parseRA3Replay($replayData) {
        $replayMagic = 'RA3 REPLAY HEADER';
        $magicLength = strlen($replayMagic);
        if(substr($replayData, 0, $magicLength) != $replayMagic) {
            throw new Exception('不是红警3录像文件');
        }
    
        // 是否是遭遇战
        $skirmishFlag = ord($replayData[$magicLength]);
    
        $index = $magicLength + 19;
        $index = self::readUTF16String($replayData, $index)['newIndex']; // 跳过
        $index = self::readUTF16String($replayData, $index)['newIndex']; // 跳过
        $mapNameHolder = self::readUTF16String($replayData, $index); // 地图名称
        $mapName = $mapNameHolder['string'];
        $index = $mapNameHolder['newIndex'];
        $index = self::readUTF16String($replayData, $index)['newIndex']; // 跳过

        $numberOfRealPlayers = ord($replayData[$index]);
        $index += 1;
        for($i = 0; $i <= $numberOfRealPlayers; ++$i) {
            $index += 4;
            $index = self::readUTF16String($replayData, $index)['newIndex'];
            if($skirmishFlag == 0x05) {
                $index += 1;
            }
        }

        $index += 38;
        $timeStamp = unpack('V', substr($replayData, $index, 4))[1]; // 时间戳
        $index += 4;
        $index += 31;
        $headerLength = unpack('V', substr($replayData, $index, 4))[1];
        $index += 4;
        $header = substr($replayData, $index, $headerLength);

        $chunks = array_chunk(preg_split('/(=|;)/', $header, -1, PREG_SPLIT_NO_EMPTY), 2);
        $array = array_combine(array_column($chunks, 0), array_column($chunks, 1));
        $mapPath = substr($array['M'], 3); // 地图路径
        $seed = (int)($array['SD']);
        $playerArray = explode(':', $array['S']);
        $players = [];
        foreach($playerArray as $playerString) {
            $playerName = explode(',', $playerString)[0];
            if($playerName[0] == 'H' || $playerName[0] == 'C') {
                $realPlayerName = substr($playerName, 1);
                if($realPlayerName != 'post Commentator') {
                    array_push($players, $realPlayerName);
                }
            }
        }

        return [
            'fileSize' => strlen($replayData),
            'mapName' => $mapName,
            'mapPath' => $mapPath,
            'players' => $players,
            'seed' => $seed,
            'timeStamp' => $timeStamp
        ];
    }

    // 返回：['string' => '读取的字符串', 'newIndex' => 读取到的位置]
    private static function readUTF16String($data, $index) {

        $string = '';
        while(true) {
            $first = $data[$index];
            $second = $data[$index + 1];
            $index += 2;
            if((ord($first) == 0) && (ord($second) == 0)) {
                break;
            }
            // 把这两个字符添加到字符串尾部
            $string .= $first;
            $string .= $second;
        }

        return [
            'string' => iconv('utf-16', 'utf-8', $string),
            'newIndex' => $index
        ];
    }
}

?>