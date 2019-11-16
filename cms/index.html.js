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
            massUploadBuffer: '',
            limit: 10,
            currentPage: 1,
        }
    },
    mounted() {
        this.checkAdmin();
        this.getTournament();
        //this.mockInit()
    },
    methods: {
        mockInit() {
            this.tournament = {"id":"84","status":"registering","players":[{"name":"Sakura","qq":"640481086","checkedIn":false,"score":"0","editingScore":false},{"name":"\u98ce\u5b50","qq":"965337727","checkedIn":false,"score":"0"},{"name":"Eutopia","qq":"1034596162","checkedIn":true,"score":"9","editingScore":false},{"name":"SovietBall","qq":"29843680","checkedIn":true,"score":"0","editingScore":false},{"name":"S.Song","qq":"1337232468","checkedIn":false,"score":"9","editingScore":false},{"name":"10\u5143\u5305\u517b\u9753\u4ed4","qq":"596603681","checkedIn":true,"score":"0","editingScore":false},{"name":"\u67aa\u624b","qq":"867633963","checkedIn":true,"score":"9","editingScore":false},{"name":"\u6a58\u732b","qq":"954701435","checkedIn":true,"score":"0","editingScore":false},{"name":"\u6b63\u592a","qq":"295454451","checkedIn":false,"score":"9","editingScore":false},{"name":"\u6a0a\u8427","qq":"2820774047","checkedIn":false,"score":"9","editingScore":false},{"name":"\u4e0d\u592a\u65b0","qq":"1277150443","checkedIn":false,"score":"6","editingScore":false},{"name":"RC","qq":"1245399328","checkedIn":false,"score":"3","editingScore":false},{"name":"\u841d\u76ae","qq":"1306211921","checkedIn":false,"score":"6","editingScore":false},{"name":"Merlin","qq":"1720819221","checkedIn":false,"score":"9","editingScore":false},{"name":"\u9a84\u9633\u4f3c","qq":"1422553135","checkedIn":false,"score":"9","editingScore":false},{"name":"\u53cc\u5203","qq":"3415612959","checkedIn":false,"score":"9","editingScore":false},{"name":"Shadow","qq":"2978646082","checkedIn":false,"score":"0"},{"name":"M","qq":"1004274970","checkedIn":false,"score":"21","editingScore":false},{"name":"\u725b\u86d9SG","qq":"1731717095","checkedIn":false,"score":"12","editingScore":false},{"name":"\u6211\u662f\u65b0\u624b","qq":"2891370096","checkedIn":false,"score":"8","editingScore":false},{"name":"\u9f20\u6807","qq":"2549858331","checkedIn":false,"score":"9","editingScore":false},{"name":"\u5c0fk","qq":"609917726","checkedIn":true,"score":"30","editingScore":false},{"name":"Maxaileiter","qq":"1143698496","checkedIn":false,"score":"6","editingScore":false},{"name":"\u767e\u57ce","qq":"845792140","checkedIn":false,"score":"21","editingScore":false},{"name":"Mikasa","qq":"552442404","checkedIn":false,"score":"0","editingScore":false},{"name":"\u84dd\u653f","qq":"2841721448","checkedIn":null,"score":"0","editingScore":false}],"creationDate":"1573307275","lastModifiedDate":"1573307275"}
        },
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
        },
        getRankIcon(points, index) {
            const icons = [
                { points: 60, id: 84 },
                { points: 45, id: 48 },
                { points: 30, id: 33 },
                { points: 20, id: 12 },
                { points: 10, id: 5 },
            ];
            const defaultValue = { id: 1 };
            const special = 87;
            if (points >= 60 && index == 0) {
                return special;
            }

            return (icons.find(x => points >= x.points) || defaultValue).id;
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
        },
        pageInfo() {
            const page = {
                list: [],
                total: 0,
                pages: 0, // 总页数
                isFirstPage: true,
                isLastPage: true,
                hasPreviousPage: false,
                hasNextPage: false,
                navigatePages: 5, // 导航页码数
                navigatepageNums: [], // 所有导航页号
                pageNum: 1, // 当前页码
                pageSize: 10, // 每页的数量
                prePage: 0, // 前一页
                nextPage: 0, // 下一页
                size: 0, // 当前页的数量
                navigateFirstPage: 0, // 第一页
                navigateLastPage: 0, // 最后一页
                startRow: 0, // 当前页面第一个元素在数据库中的行号，从0开始
                endRow: 0 // 当前页面最后一个元素在数据库中的行号
            };
            page.total  = this.tournament.players.length;
            page.pageSize = this.limit;
            page.pages = Math.ceil(page.total / page.pageSize);
            page.pageNum = this.currentPage < 1 ? 1 : this.currentPage > page.pages ? page.pages : this.currentPage;
            page.navigatePages = 5;
            page.list = this.tournament.players.slice(page.pageSize * (page.pageNum-1), page.pageSize * (page.pageNum-1) + page.pageSize)
            page.size =  page.pageNum == page.pages ? page.total % (page.pages-1) : page.pageSize
            page.startRow = (page.pageNum-1) * page.pageSize;
            page.endRow = page.startRow + page.size - 1;

            /* 计算导航页 */
            var pages = page.pages
            var navigatePages = page.navigatePages
            // 当总页数小于或等于导航页时，导航页直接 1 ~ n+1
            if(pages <= navigatePages) {
                for(var i = 0; i < pages; i++) {
                    page.navigatepageNums[i] = i + 1;
                }
            } else { // 当总页数大于导航页码数时
                var startNum = page.pageNum - Math.ceil(page.navigatePages / 2);
                var endNum = page.pageNum + Math.ceil(page.navigatePages / 2);
                if (startNum < 1) {
                    startNum = 1;
                    //(最前navigatePages页
                    for (var i = 0; i < navigatePages; i++) {
                        page.navigatepageNums[i] = startNum++;
                    }
                } else if (endNum > pages) {
                    endNum = pages;
                    //最后navigatePages页
                    for (var i = navigatePages - 1; i >= 0; i--) {
                        page.navigatepageNums[i] = endNum--;
                    }
                } else {
                    //所有中间页
                    for (var i = 0; i < navigatePages; i++) {
                        page.navigatepageNums[i] = startNum++;
                    }
                }
            }

            /* 计算前后页，第一页，最后一页 */
            page.navigateFirstPage = page.navigatepageNums[0];
            page.navigateLastPage = page.navigatepageNums[page.navigatepageNums.length - 1]
            if (page.pageNum > 1) {
                page.prePage = page.pageNum - 1;
            }
            if (page.pageNum < page.pages) {
                page.nextPage = page.pageNum + 1;
            }

            /* 判断页面边界 */
            page.isFirstPage = page.pageNum == 1
            page.isLastPage = page.pageNum == page.pages || page.pages == 0
            page.hasPreviousPage = page.pageNum > 1;
            page.hasNextPage = page.pageNum < page.pages;

            return page;
        }
    }
})