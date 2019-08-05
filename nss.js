function login() {
    var transdata = {
        'username': document.getElementById("login_username").value,
        'password': document.getElementById("login_password").value
    };

    //temp is a temporary value of feedback
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

function accessLevel() {
    var existCookie = "0";
    var allCookie = decodeURIComponent(document.cookie);
    var splitedCookie = allCookie.split(";");
    for (var i = 0; i < splitedCookie.length; i++) {
        if (splitedCookie[i].indexOf("token=") == 0) {
            existCookie = splitedCookie[i].substring(6, splitedCookie[i].length);
        }
    }

    fetch("nss.php?do=getAccessLevel&token=" + existCookie)
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

// function list_judgers() {

// }

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