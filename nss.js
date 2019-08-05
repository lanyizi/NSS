//function login() {
var transdata = document.getElementById(login_info);

//temp is a temporary value of feedback
var temp = fetch("/nss.php?do=login", {
        method: 'post',
        body: JSON.stringify(transdata),
        headers: { 'Content-Type': 'application/json' }
    })
    .then(res => res.json());

//If not pass
if (temp = '0') {
    alert("Wrong username or password")
        //window.location.href = "login_page.html";
    return "Login Fail";
}

//Get the time and set for 2 hours access
var date = new Date();
date.setTime(date.getTime() + (2 * 60 * 60 * 1000));

//Get token
var feedback = JSON.parse(temp);
var token = feedback[Object.keys(feedback)[1]];

//Set cookie
document.cookie = "Token=" + token + "; expires=" + date.toGMTString();

return "Login Success";
//}

// function accesslevel() {

// }

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