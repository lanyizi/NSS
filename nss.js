function getLocalToken() {
    let cookies = new Map(
        decodeURIComponent(document.cookie)
        .split(';') // 按照';'把 cookie 分成一个数组
        .map(splitted => splitted.split('=', 2))
        // 然后按照'='把每个部分再分成两部分（key 和 value）
        // 并把这些 key 和 value 转换成一个 Map
    );

    // 从 cookies 获取 token 的值，假如没有的话就用"0"作为 token 的值
    return (cookies.get('token') || "0");
}

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
            return res.json();
        })
        .then(feedback => {
            if (feedback.result) {
                alert(feedback.message);
                window.location.href = "admincontrol.html";
                return "Set success";
            }
            alert(feedback.message);
            window.location.href = "admincontrol.html";
            return "Set failed";
        });
}

function judgePlayer() {
    //Set json
    transdata = {
        'token': getLocalToken(),
        'name': document.getElementById("setName").value,
        'nickname': document.getElementById("setNickname").value,
        'level': document.getElementById("setLevel").value,
        'qq': document.getElementById("setQQ").value,
        'judgeDate': Math.floor(Date.now() / 1000),
        'faction': document.getElementById("setFaction").value,
        'replays': document.getElementById("setReplays").value,
        'description': document.getElementById("setDescription").value
    }

    fetch("nss.php?do=judgePlayer", {
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
            if (feedback.result) {
                alert(feedback.message)
                window.location.href = "judgecontrol.html";
                return "Judge success";
            }
            alert(feedback.message);
            window.location.href = "judgecontrol.html";
            return "Judge failed";
        });
}

function removePlayer() {
    transdata = {
        'token': getLocalToken(),
        'id': document.getElementById("removeId").value
    }

    fetch("nss.php?do=removePlayer", {
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
            if (feedback.result) {
                alert(feedback.message)
                window.location.href = "judgecontrol.html";
                return "Remove success";
            }
            alert(feedback.message);
            window.location.href = "judgecontrol.html";
            return "Remove failed";
        });
}