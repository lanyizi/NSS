function getLocalToken() {
    let cookies = new Map(
        decodeURIComponent(document.cookie)
            .split(';') // 按照';'把 cookie 分成一个数组
            .map(splitted => splitted.split('=', 2))
        // 然后按照'='把每个部分再分成两部分（key 和 value）
        // 并把这些 key 和 value 转换成一个 Map
    );
    
    // 从 cookies 获取 token 的值，假如没有的话就用"0"作为 token 的值
    let token = cookies.get('token') || "0";
}

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
            // 每个回调函数都有自己的 this
            // 在 then 里面的这些函数里再使用 this 就不能再指向
            // Vue 实例了，所以我们先把当前的 this 保存为另一个变量
            // 以供在回调函数里使用
            let self = this;
            fetch("nss.php?do=getAccessLevel&token=" + token)
                .then(response => response.json())
                .then(response => {
                    // 设置数据
                    self.username = response.username;
                    self.accessLevel = response.accessLevel;
                });
        },
        listJudgers: function () {
            fetch("nss.php?do=getJudgers")
                .then(response => {
                    return response.json();
                })
                .then(response => {
                    // 设置数据
                    self.judgers = response.judgers;
                });
        },
        listPlayers: function () {
            fetch("nss.php?do=getPlayers")
                .then(response => response.json())
                .then(response => {
                    // 设置数据
                    self.players = response.players;
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
        'username': document.getElementById("loginUsername").value,
        'password': document.getElementById("loginPassword").value
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
                window.location.href = "login.html";
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

function setJudgers() {
    //Set json
    transdata = {
        'token': getLocalToken(),
        'username': document.getElementById("setUsername").value,
        'password': document.getElementById("setPassword").value,
        'accessLevel': document.getElementById("accessLevel").value,
        'description': document.getElementById("description").value
    }

    fetch("nss.php?do=setJudger", {
            method: 'post',
            body: JSON.stringify(transdata),
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(res => {
            return res.json;
        })
        .then(feedback => {
            if (feedback.result) {
                alert("Set success")
                window.location.href = "admincontrol.html";
                return "Set success";
            }
            alert("Set failed");
            window.location.href = "admincontrol.html";
            return "Set failed";
        });
}



// function list_players(token, name, password, accesslevel, description) {

// }

// function judge_player(token, name, nickname, level, qq, judgedate, faction, replaylinks, description) {

// }

// function remove_player(token, name) {

// }

// function upload_replay(filename, data) {

// }