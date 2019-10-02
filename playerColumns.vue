<template>
    <div v-bind:class="playerColumnClasses">
        <!-- 名称和昵称 -->
        <span v-if="computedType == 'nameAndNickname'">
            {{ computedNameAndNickname }}
        </span>
        <!-- 头像 -->
        <img v-else-if="computedType == 'avatar'" v-bind:src="computedAvatar" />
        <!-- 查看 / 设置录像 -->
        <div v-else-if="computedType == 'replays'" >
            <button v-if="computedLabel != null" v-on:click.stop="$emit('replay-click')">
                {{ computedLabel }}
            </button>
        </div>
        <!-- 不可修改的阵营图标 -->
        <ul v-else-if="computedType == 'faction'">
            <li v-for="data in computedActiveFactions" v-bind:key="data.faction" v-bind:class="data.class">
                <img v-bind:src="data.iconSrc" v-bind:alt="data.faction"/>
            </li>
        </ul>
        <!-- 不可修改的鉴定日期 -->
        <span v-else-if="computedType == 'judgeDate'">
            {{ computedDate }}
        </span>
        <!-- 不可修改的鉴定级别 -->
        <span v-else-if="computedType == 'level'">
            {{ computedLevelText }}
        </span>
        <!-- 可修改的名称/昵称查询器（修改名称） -->
        <input v-else-if="computedType == 'input-nameAndNickname'" v-model="computedNameAndNickname" v-bind:placeholder="computedLabel"/>
        <!-- 可修改的阵营图标 -->
        <ul v-else-if="computedType == 'input-faction'">
            <li v-for="data in computedFactionData" v-bind:key="data.faction">
                <button v-on:click="toggleFaction(data)">
                    <img v-bind:src="data.iconSrc" v-bind:alt="data.faction" />
                </button>
            </li>
        </ul>
        <!-- 可修改的鉴定日期 -->
        <input v-else-if="computedType == 'input-judgeDate'" type="date" v-model="computedDate" />
        <!-- 可修改的鉴定级别 -->
        <input v-else-if="computedType == 'input-level'" type="number" step="0.5" v-model="computedLevel" v-bind:placeholder="computedLabel" />
        <!-- 可修改的普通文本 -->
        <input v-else-if="editable" type="text" v-bind:value="value[type]" v-on:input="update($event.target.value)" v-bind:placeholder="computedLabel"/>
        <!-- 不可修改的普通文本 -->
        <span v-else>
            {{ value[type] }}
        </span>
    </div>
</template>
<script>
module.exports = {
    data: function() {
        return {
            factionMapper: [
                { faction: 'Allies', id: 4 },
                { faction: 'Soviets', id: 8 },
                { faction: 'Empire', id: 2 },
            ]
        };
    },
    props: {
        type: {
            required: true,
            type: String
        },
        value: {
            required: true,
            type: Object
        },
        editable: {
            type: Boolean,
            default: false
        },
        factionIconFormat: {
            type: String,
            default: ''
        }
    },
    computed: {
        playerColumnClasses: function() {
            return [ 'player-column', 'player-column-' + this.type ];
        },
        computedType: function() {
            const specials = ['avatar', 'replays'];
            if(this.editable && specials.every(type => this.type != type)) {
                return 'input-' + this.type;
            }
            return this.type;
        },
        computedAvatar: function() {
            // default: transparent
            const transparent = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=";
            if(typeof(this.value.avatar) != 'string') {
                return transparent;
            }
            
            return Object.values(JSON.parse(this.value.avatar.slice(13, -1)))[0] || transparent;
        },
        computedNameAndNickname: {
            get: function() {
                if(!this.value.nickname) {
                    return this.value.name;
                }
                return this.value.name + ' (' + this.value.nickname + ')';
            },
            set: function(value) {
                this.updateNameAndNicknameField(value);
            }
        },
        computedLevel: {
            get: function() {
                return this.value.level;
            },
            set: function(value) {
                if((value >= 4) && ((value % 1) != 0)) {
                    return;
                }

                if((value % 0.5) != 0) {
                    return;
                }

                this.update(value);
            }
        },
        computedLevelText: function() {
            const decimalPart = this.computedLevel % 1;
            const integerLevel = Math.round(this.computedLevel - decimalPart);
            return integerLevel + (decimalPart == 0 ? '' : '+');
        },
        computedDate: {
            get: function() {
                const unixTimeStamp = this.value.judgeDate
                if(!unixTimeStamp) {
                    return '';
                }

                const date = (new Date(unixTimeStamp * 1000));
                const methods = ['getFullYear', 'getMonth', 'getDate'];
                const offsets = [0, 1, 0];
                const padding = [4, 2, 2];
                return methods
                    .map(fn => date[fn]())
                    .map((value, index) => (value + offsets[index]).toString())
                    .map((string, index) => '0'.repeat(Math.max(padding[index] - string.length, 0)) + string)
                    .join('-');
            },
            set: function(value) {
                if(!value) {
                    this.update(null);
                    return;
                }
                const [year, month, day] = value.split('-').map(x => parseInt(x));
                const judgeDate = parseInt((new Date(year, month - 1, day)).getTime() / 1000);
                this.update(judgeDate);
            }
        },
        computedFactionData: function() {
            return this.factionMapper.map(data => {
                data.active = this.value.faction.includes(data.faction);
                data.class = data.active ? "active-faction" : "inactive-faction";
                data.iconSrc = this.factionIconFormat.replace('*', data.id);
                return data;
            });
        },
        computedActiveFactions: function() {
            return this.computedFactionData.filter(data => data.active);
        },
        computedLabel: function() {
            if(this.type == 'replays') {
                if(this.value.replays.length == 0) {
                    return this.editable ? '添加录像' : null; 
                }
                return this.editable ? '编辑录像' : '查看录像'
            }
            const names = {
                name: '玩家名称',
                nickname: '昵称（可选）', 
                nameAndNickname: '玩家名称/昵称',
                level: '级别',
                qq: 'QQ号',
                judger: '鉴定员',
                faction: '主阵营',
                description: '说明（可选）'
            };
            return names[this.type] || this.type;
        }
    },
    methods: {
        update: function(value) {
            if(!this.editable) {
                return;
            }
            
            let updated = JSON.parse(JSON.stringify(this.value));
            updated[this.type] = value;
            this.$emit('input', updated);
        },
        updateNameAndNicknameField: function(value) {
            let updated = JSON.parse(JSON.stringify(this.value));
            updated.nickname = '';
            updated.name = value;
            this.$emit('input', updated);
        },
        toggleFaction: function(data) {
            let newFactions = this.value.faction
                .filter(faction => {
                    return (faction != data.faction) || (!data.active);
                });

            if(!data.active) {
                newFactions = newFactions.concat(data.faction);
            }

            this.update(newFactions.sort());
        }
    }
}
</script>
