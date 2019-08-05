// 创建一个vue实例
let nss = new Vue({
    el: '#nss', // 与vue关联的元素是html里id为nss的那个div
    data: { // 数据
        accessLevel: 0,
        username: '',
        judgers: [], // 一开始，鉴定员列表是个空数组
        players: [], // 一开始，玩家列表是个空数组
    },
    mounted: function () { // mounted 会在vue实例被挂载后被调用
        getAccessLevel();
        listJudgers();
    },
    methods: { // 在这里放你的Vue实例的所有的方法，
        // 注意Vue的methods只能是
        // xxx: function () { ... } 这样子的，
        // 而不能是 xxx => { ... }，
        // 因为与前者不同，后者的 'this' 将不会是 Vue 实例
        // （见 https://vuejs.org/v2/api/#methods ）
        getAccessLevel: function () {
            //Get and decode cookie
            let cookies = new Map(
                decodeURIComponent(document.cookie)
                    .split(';') // 按照';'把 cookie 分成一个数组
                    .map(splitted => splitted.split('=', 2))
                // 然后按照'='把每个部分再分成两部分
                // 并把它转换成一个 Map，这时'='左边的部分就是 key
                // 右边的部分则是 value
            );
            
            // 从 cookies 获取 token 的值，假如没有的话就用"0"作为 token 的值
            let token = cookies.get('token') || "0";

            //Fetch, and change html
            fetch("nss.php?do=getAccessLevel&token=" + token)
                .then(response => response.json())
                .then(response => {
                    // 设置数据
                    this.username = response.username;
                    this.accessLevel = response.accessLevel;
                });
        },
        listJudgers: function () {
            fetch("nss.php?do=getJudgers")
                .then(response => {
                    return response.json();
                })
                .then(response => {
                    // 设置数据
                    this.judgers = response.judgers;
                });
        },
        listPlayers: function () {
            fetch("nss.php?do=getPlayers")
                .then(response => response.json())
                .then(response => {
                    // 设置数据
                    this.players = response.players;
                });   
        },
        showReplayList: function(replays) {
            alert('还没做');
        }
    }
});

function login() {
    //Set json
    var transdata = {
        'username': document.getElementById("login_username").value,
        'password': document.getElementById("login_password").value
    };

    //Fetch, show result, and set cookie
    fetch("nss.php?do=login", {
            method: 'post',
            body: JSON.stringify(transdata),
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(res => {
            return res.json();
        })
        .then(feedback => {
            if (feedback.token == '0') {
                alert("Wrong username or password")
                window.location.href = "login_page.html";
                return "Login Fail";
            }
            alert("Login Success")
                //Get the time and set for 2 hours access
            var date = new Date();
            date.setTime(date.getTime() + (2 * 60 * 60 * 1000));

            //Get token

            var token = feedback.token;

            //Set cookie
            document.cookie = "token=" + token + "; expires=" + date.toGMTString();

            //Back to index
            window.location.href = "index.html";
            return "Login Success";
        });
}



// function set_judgers() {

// }

// function list_players(token, name, password, accesslevel, description) {

// }

// function judge_player(token, name, nickname, level, qq, judgedate, faction, replaylinks, description) {

// }

// function remove_player(token, name) {

// }

// function upload_replay(filename, data) {

// }