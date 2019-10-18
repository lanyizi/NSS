<?php
require_once('../Medoo-master/src/Medoo.php');

use Medoo\Medoo;

function main() {
    try {
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
            'prefix' => 'nss_cms_',
            'option' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        ]);

        $cms = new CMS($input, $database);
        return $cms->doAction($_GET['do']);
    }
    catch(Exception $exception) {
        http_response_code(500);
        return [
            'exception' => $exception->getMessage()
        ];
    }
}

class CMS {
    /**
     * @var \Medoo\Medoo
     */
    private $database;

    private $input;

    private $verified;

    public function __construct($input, Medoo $database) {
        $this->input = $input;
        $this->database = $database;
        $this->database->create('tokens', [
            'token' => [
                'TEXT',
                'NOT NULL'
            ], 
            'description' => [
                'TEXT',
                'NOT NULL'
            ]
        ]);
        $this->database->create('tournaments', [
            'id' => [
                'INT',
                'NOT NULL',
                'AUTO_INCREMENT',
                'PRIMARY KEY'
            ],
            'status' => [
                'TEXT',
                'NOT NULL'
            ],
            'players' => [
                'TEXT',
                'NOT NULL'
            ],
            'creationDate' => [
                'BIGINT',
                'NOT NULL'
            ],
            'lastModifiedDate' => [
                'BIGINT',
                'NOT NULL'
            ]
        ]);
        if(empty($this->input['token'])){
            $this->verified = false;
        }
        else {
            $this->verified = $this->database->has('tokens', [
                'token' => $this->input['token']
            ]);
        }
    }

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

    public function checkToken() {
        return [
            'verified' => $this->verified
        ];
    }

    public function getLastTournament() {
        if(!$this->database->has('tournaments', [ 'id[!]' => null ])) {
            $this->newTournament();
        }

        $tournament = $this->database->get('tournaments', [
            'id',
            'status',
            'players',
            'creationDate',
            'lastModifiedDate'
        ], [
            'ORDER' => [ 'id' => 'DESC' ]
        ]);

        if(is_array($tournament) && is_string($tournament['players'])) {
            $tournament['players'] = json_decode($tournament['players'], true);
            if(!is_array($tournament['players'])) {
                $tournament['players'] = [];
            }
        }
        return [
            'tournament' => $tournament
        ];
    }

    public function newTournament() {
        if(!$this->verified) {
            return [
                'succeeded' => false,
                'message' => 'Token unverified'
            ];
        }

        $previousPlayers = $this->database->get('tournaments', [
            'players'
        ], [
            'ORDER' => [ 'id' => 'DESC' ]
        ]);
        if(is_string($previousPlayers)) {
            $previousPlayers = json_decode($previousPlayers, true);
        }
        if(is_array($previousPlayers)) {
            $previousPlayers = [];
        }
        $this->database->insert('tournaments', [
            'status' => 'registering',
            'players' => json_encode($previousPlayers),
            'creationDate' => time(),
            'lastModifiedDate' => time()
        ]);

        return [
            'succeeded' => true,
            'message' => 'Operation succeeded'
        ];
    }

    public function modifyLastTournament() {
        $tournament = $this->getLastTournament()['tournament'];
        $newData = $this->input['tournament'];
        if($tournament['id'] != $newData['id']) {
            return [
                'succeeded' => false,
                'message' => 'outdated id'
            ];
        }

        foreach($newData as $key => $value) {
            if($key == 'status' && is_string($value)) {
                $tournament[$key] = $value;
            }
            else if($key == 'players' && is_array(($value))) {
                $tournament[$key] = json_encode($value);
            }
        }
        $tournament['lastModifiedDate'] = time();
        $id = $tournament['id'];
        unset($tournament['id']);
        $this->database->update('tournaments', $tournament, [
            'id' => $id
        ]);

        return [
            'succeeded' => true,
            'message' => 'Operation succeeded'
        ];
    }
}

echo json_encode(main());
?>