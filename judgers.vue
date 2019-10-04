<template>
    <div class="judgers-container">
        <div>
            <form v-if="!localValue.accessLevel" v-on:submit.stop="login()">
                <input type="text" name="username" v-model="input.username" placeholder="鉴定员用户名">
                <input type="password" name="password" v-model="input.password" placeholder="密码">
                <input type="submit" value="登录">
            </form>
            <div v-else>
                当前身份：{{ localValue.username }}
                <button v-on:click="localToken = null; updateAccessLevel()">
                    退出
                </button>
            </div>
        </div>
        <table>
            <thead>
                <th>鉴定员名称</th>
                <th>个人信息</th>
                <th>
                    <div id="setJudger" v-if="localValue.accessLevel > 0">
                        <a href="admincontrol.html">编辑鉴定员</a>
                    </div>
                </th>
            </thead>
            <tbody>
                <tr v-for="row in judgers" v-bind:key="row.username">
                    <td>{{ row.username }}</td>
                    <td colspan="2">{{ row.description }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
<script>
module.exports = {
    data: function() {
        return {
            input: {
                username: '',
                password: '',
            }
        };
    },
    props: {
        value: {
            required: true,
            type: Object
        },
    },
    computed: {
        localToken: {
            get: function() {
                const cookies = new Map(
                    decodeURIComponent(document.cookie)
                    .split(';') // 按照';'把 cookie 分成一个数组
                    .map(splitted => splitted.split('=', 2))
                    // 然后按照'='把每个部分再分成两部分（key 和 value）
                    // 并把这些 key 和 value 转换成一个 Map
                );
                const token = (cookies.get('token') || '0');
            },
            set: function(value) {
                if(!value || value == '0') {
                    document.cookie = 'token=0; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
                    return;
                }

                let date = new Date();
                date.setDate(date.getDate() + 1);
                document.cookie = 'token=' + value + '; expires=' + date.toUTCString();
            }
        }, 
        localValue: function() {
            const valid = this.value && this.value.username && this.value.accessLevel;
            return vaild ? this.value : { username: '游客', accessLevel: 0 };
        }
    },
    methods: {
        updateAccessLevel: function() {
            const token = getLocalToken();
            fetch('nss.php?do=getAccessLevel&token=' + token)
                .then(response => response.json())
                .then(response => {
                    // 设置数据
                    this.$emit('input', {
                        username: response.username,
                        accessLevel: response.accessLevel,
                        token: token
                    });
                });
        },
        login: function() {
            fetch('nss.php?do=login', {
                method: 'post',
                body: JSON.stringify(this.input),
                headers: {
                    'Content-type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(result => {
                if(!result || !result.token || result.token == '0') {
                    throw new Error('登录失败');
                }
                localToken = token;
                this.updateAccessLevel();
            })
            .catch(reason => alert(reason));
        },
        listJudgers: function() {
            fetch('nss.php?do=getJudgers')
                .then(response => {
                    return response.json();
                })
                .then(response => {
                    // 设置数据
                    this.judgers = response.judgers;
                });
            setInterval(60000, this.listJudgers);
        },
    },
    mounted: function() {
        this.updateAccessLevel();
        this.listJudgers();
    }
}
</script>