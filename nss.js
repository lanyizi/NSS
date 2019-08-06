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

function accessLevel() {
    //Fetch, and change html
    fetch("nss.php?do=getAccessLevel&token=" + getLocalToken())
        .then(res => {
            return res.json();
        })
        .then(feedback => {
            document.getElementById("showUsername").innerHTML = feedback.username;
            document.getElementById('toLoginPage').style.visibility = 'hidden';
            if (feedback.accessLevel == 0) {
                document.getElementById('toLoginPage').style.visibility = 'visible';
            }
        });
}

function listJudgers() {
    fetch("nss.php?do=getJudgers")
        .then(res => {
            return res.json();
        })
        .then(feedback => {
            for (var i = 0; i < feedback.judgers.length; i++) {
                var table = document.getElementById("judgersInfoTable");

                //Insert row
                var newRow = table.insertRow(i + 1);

                //Insert cells of the row
                var cellName = newRow.insertCell(0)
                cellName.innerHTML = feedback.judgers[i].username;

                var cellDescription = newRow.insertCell(1)
                cellDescription.innerHTML = feedback.judgers[i].description;
            }
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
                alert("Set success")
                window.location.href = "admincontrol.html";
                return "Set success";
            }
            alert("Set failed");
            window.location.href = "admincontrol.html";
            return "Set failed";
        });
}

function getLocalToken() {
    //Set default token
    var token = "0";

    //Get and decode cookie
    var allCookie = decodeURIComponent(document.cookie);
    var splitedCookie = allCookie.split(";");

    //Find cookie "token"
    for (var i = 0; i < splitedCookie.length; i++) {
        if (splitedCookie[i].indexOf("token=") == 0) {
            token = splitedCookie[i].substring(6, splitedCookie[i].length);
        }
    }

    return token;
}

// function list_players(token, name, password, accesslevel, description) {

// }

// function judge_player(token, name, nickname, level, qq, judgedate, faction, replaylinks, description) {

// }

// function remove_player(token, name) {

// }

// function upload_replay(filename, data) {

// }