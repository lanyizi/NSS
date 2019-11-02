const app = new Vue({
    el: "#app",
    data() {
        return {
            currentToken: null,
            isAdmin: false,
            tournament: {
                id: null,
                status: null,
                players: [],
                lastModifiedDate: null
            },
            nextNewPlayer: {
                name: null,
                qq: null,
                checkedIn: null,
                score: null
            },
            nextNewPlayerIndex: null,
            enableMassUpload: false,
            massUploadBuffer: ''
        }
    },
    mounted() {
        this.checkAdmin();
        this.getTournament();
    },
    methods: {
        tournamentTemplate() {
            return {
                id: null,
                status: null,
                players: [],
                lastModifiedDate: null
            };
        },
        playerTemplate() {
            return {
                name: null,
                qq: null,
                checkedIn: false,
                score: null,
                editingScore: false,
            }
        },
        checkAdmin() {
            const currentToken = this.token;
            fetch('cms.php?do=checkToken', {
                method: 'post',
                body: JSON.stringify({ token: currentToken }),
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(result => {
                this.isAdmin = result.verified;
                if(this.isAdmin) {
                    let date = new Date();
                    date.setDate(date.getDate() + 1);
                    document.cookie = 'cmsadmintoken=' + currentToken + '; expires=' + date.toUTCString();
                }
                else {
                    document.cookie = 'cmsadmintoken=0; expires=Thu, 01 Jan 1970 00:00:01 GMT;';    
                }
            })
        },
        getTournament() {
            fetch('cms.php?do=getLastTournament')
            .then(response => response.json())
            .then(result => {
                if(!result) {
                    this.tournament = this.tournamentTemplate();
                    return;
                }
                this.tournament = result.tournament || this.tournamentTemplate();
            });
        },
        updateTournament() {
            fetch('cms.php?do=modifyLastTournament', {
                method: 'post',
                body: JSON.stringify({ 
                    token: this.token,
                    tournament: this.tournament 
                }),
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(result => {
                if(result.succeeded) {
                    // alert('操作成功');
                }
                else {
                    alert('操作失败：' + result.message);
                }
                this.getTournament();
            })
            .catch(reason => {
                alert('错误：' + reason);
                this.getTournament();
            });
        },
        getPlayerIndex(player) {
            return this.tournament.players.findIndex(item => item.name == player.name);
        },
        playerOffset(player, offset) {
            const index = this.getPlayerIndex(player);
            if(index == -1) {
                alert('找不到该玩家，可能已经被删除了');
                return;
            }

            this.tournament.players.splice(index, 1);
            const newIndex = Math.max(Math.min(index + offset, this.tournament.players.length), 0);
            this.tournament.players.splice(newIndex, 0, player);
            this.updateTournament();
        },
        playerToggleCheckIn(player) {
            player.checkedIn = !player.checkedIn;
            this.updateTournament();
        },
        playerDelete(player) {
            if(!confirm('确认删除？')) {
                return;
            }

            const index = this.getPlayerIndex(player);
            if(index == -1) {
                alert('找不到该玩家，可能已经被删除了');
                return;
            }
            this.tournament.players.splice(index, 1);
            this.updateTournament();
        },
        playerAdd() {
            const newPlayer = this.nextNewPlayer;
            const newPlayerIndex = this.nextNewPlayerIndex;
            this.nextNewPlayer = this.playerTemplate();
            this.nextNewPlayerIndex = null;
            if(newPlayerIndex != null) {
                const max = this.tournament.players.length;
                const clamped = Math.max(Math.min(Math.round(newPlayerIndex), max), 0);
                this.tournament.players.splice(clamped, 0, newPlayer);
            }
            else {
                this.tournament.players.push(newPlayer);
            }
            this.updateTournament();
        },
        massUpload() {
            this.massUploadBuffer
            .split('\n')
            .filter(line => line.length != 0)
            .forEach(line => {
                const columns = line.trim().split(' ')
                    .filter(column => column.length != 0);
                const player = this.playerTemplate();
                player.name = columns[0];
                player.qq = columns[1];
                player.score = parseInt(columns[2]);
                this.tournament.players.push(player);
            });
            this.updateTournament();
        },
        newTournament() {
            fetch('cms.php?do=newTournament', {
                method: 'post',
                body: JSON.stringify({ token: this.token }),
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(result => {
                if(result.succeeded) {
                    // alert('操作成功');
                }
                else {
                    alert('操作失败：' + result.message);
                }
                this.getTournament();
            })
            .catch(reason => {
                alert('错误：' + reason);
                this.getTournament();
            });
        }
    },
    computed: {
        token: {
            get() {
                if(!this.currentToken) {
                    const cookies = new Map(
                        decodeURIComponent(document.cookie)
                        .split(';')
                        .map(splitted => splitted.split('=', 2))
                    );
                    this.currentToken = (cookies.get('cmsadmintoken') || '');
                }
                return this.currentToken;
            },
            set(value) {
                this.currentToken = value;
                this.isAdmin = false;
                if(!this.currentToken) {
                    document.cookie = 'cmsadmintoken=0; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
                    return;
                }

                this.checkAdmin();
            }
        },
        bracket() {
            const matchTemplate = {
                first: null,
                second: null,
                secondRound: null
            };

            const checkedIn = this.tournament.players.filter(player => player.checkedIn);
            const numberOfPlayers = checkedIn.length;
            const isOddFormal = (this.tournament.id % 2) == 0;
            const isOdd = isOddFormal && (checkedIn.length > 2);
            const firstExtraPlayer = isOdd ? checkedIn.shift() : null;
            const secondExtraPlayer = (checkedIn.length % 2 != 0) ? checkedIn.pop() : null;
            
            const matches = checkedIn.reduce((matches, player) => {
                const last = matches[matches.length - 1];
                if(last.first == null) {
                    last.first = player.name;
                }
                else {
                    last.second = player.name;
                    const newMatch = JSON.parse(JSON.stringify(matchTemplate));
                    matches.push(newMatch);
                }
                return matches;
            }, [ JSON.parse(JSON.stringify(matchTemplate)) ]);
            
            if(matches[matches.length - 1].first == null && matches.length > 1) {
                matches.pop();
            }

            if(firstExtraPlayer != null) {
                matches[0].secondRound = firstExtraPlayer.name;
            }
            if(secondExtraPlayer != null) {
                const currentLast = matches[matches.length - 1];
                if(currentLast.secondRound == null) {
                    currentLast.secondRound = secondExtraPlayer.name;
                }
                else {
                    const newLast = JSON.parse(JSON.stringify(matchTemplate));
                    newLast.first = secondExtraPlayer.name;
                    newLast.second = '玩家人数过少，目前的轮空模式导致无法生成正确的对阵表，请考虑重新生成比赛';
                    matches.push(newLast);
                }
            }

            return {
                isOdd: isOdd,
                tooLessPeople: numberOfPlayers < 5,
                matches: matches
            };
        },
        isMassUploadBufferInvalid() {
            const splitted = this.massUploadBuffer.split('\n').filter(line => line.length != 0);
            return splitted.length == 0 || splitted.some(line => {
                const columns = line.trim().split(' ').filter(column => column.length != 0);
                if(columns.length != 3) {
                    return true;
                }
                if(isNaN(parseInt(columns[1])) || isNaN(parseInt(columns[2]))) {
                    return true;
                }
                return false;
            });
        }
    }
})