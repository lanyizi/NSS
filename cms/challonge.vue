<template>
    <div>
        <table>
            <tr>
                <td>比赛链接</td>
                <td>
                    <input type="text" v-if="isAdmin" v-model="lastTournament.link" />
                    <a v-else :src="lastTournament.link" >{{ lastTournament.link }}</a>
                </td>
            </tr>
            <tr v-if="isAdmin">
                <td>Challonge参数</td>
                <td>
                    <input type="text" v-model="lastTournament.additionalParameters" />
                </td>
            </tr>
            <tr>
                <td>比赛说明</td>
                <td>
                    <input type="text" :readonly="!isAdmin" v-model="lastTournament.description" />
                </td>
            </tr>
            <tr>
                <td>修改日期</td>
                <td>{{ lastTournamentDate }}</td>
            </tr>
            <tr v-if="isAdmin">
                <button @click="setChallonge">修改比赛</button>
            </tr>
        </table>
        <br />
        <iframe :src="challongeIframeLink" width="100%" height="500" frameborder="0" scrolling="auto" allowtransparency="true"></iframe>
    </div>
</template>

<script>
module.exports = {
    data: function() {
        return {
            lastTournament: {
                link: '',
                additionalParameters: '',
                description: '',
                timeStamp: ''
            }
        };
    },
    props: {
        isAdmin: Boolean,
        token: String,
    },
    computed: {
        /** @returns {Date} */
        lastTournamentDate: function() {
            return new Date(this.lastTournament.timeStamp * 1000);
        },
        /** @returns {string} */
        challongeIframeLink: function() {
            /** @type {string} */
            let link = this.lastTournament.link;
            if (!link.endsWith('/')) {
                link += '/';
            }
            link += 'module';
            if (this.lastTournament.additionalParameters) {
                link += '?';
                link += this.lastTournament.additionalParameters;
            }
            return link;
        }
    },
    methods: {
        /** @returns {void} */
        getChallonge: function() {
            const self = this;
            fetch('cms.php?do=getChallongeLink')
                .then(function(response) { return response.json() })
                .then(function(data) { Object.assign(self.lastTournament, data.challonge); });
        },
        /** @returns {void} */
        setChallonge: function() {
            const self = this;

            this.lastTournament.timeStamp = '0';
            const body = { token: this.token }; 
            Object.assign(body, this.lastTournament);
            fetch('cms.php?do=setChallongeLink', {
                method: 'post',
                headers: { 'Content-Type': 'application/json; charset=utf-8' },
                body: JSON.stringify(body)
            })
                .then(function(response) { return response.json() })
                .then(function(data) { alert(data.message); self.getChallonge(); })
                .catch(function(why) { alert(why); self.getChallonge(); });
        },
    },
    /** @returns {void} */
    created: function() {
        this.getChallonge();
    },
}
</script>

<style src="challonge.css">

</style>