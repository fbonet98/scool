<template>
    <v-select
            :name="name"
            :label="label"
            :items="users"
            v-model="internalUser"
            item-text="name"
            :item-value="itemValue"
            chips
            autocomplete
            clearable
            @input="input"
            @blur="blur"
            :error-messages="errorMessages"
    >
        <template slot="selection" slot-scope="data">
            <v-chip
                    @input="data.parent.selectItem(data.item)"
                    :selected="data.selected"
                    class="chip--select-multi"
                    :key="JSON.stringify(data.item)"
            >
                <user-avatar :hash-id="data.item.hashid"
                             :alt="data.item.name"
                ></user-avatar>
                {{ data.item.name }}
            </v-chip>
        </template>
        <template slot="item" slot-scope="{ item: user }">
            <v-list-tile-avatar>
                <img :src="'/user/' + user.hashid + '/photo'">
            </v-list-tile-avatar>
            <v-list-tile-content>
                <v-list-tile-title v-html="user.name"></v-list-tile-title>
                <v-list-tile-sub-title v-html="user.email"></v-list-tile-sub-title>
            </v-list-tile-content>
        </template>
    </v-select>
</template>

<style>

</style>

<script>
  import UserAvatar from '../ui/UserAvatarComponent'

  export default {
    components: {
      'user-avatar': UserAvatar
    },
    data () {
      return {
        internalUser: this.user
      }
    },
    model: {
      prop: 'user',
      event: 'input'
    },
    props: {
      name: {
        type: String,
        default: 'user'
      },
      user: {},
      label: {
        type: String,
        default: 'Escolliu un usuari'
      },
      users: {
        type: Array,
        required: true
      },
      itemValue: {
        type: String,
        default: 'id'
      },
      errorMessages: {
        type: Array,
        required: false
      }
    },
    watch: {
      user (newUser) {
        this.internalUser = newUser
      }
    },
    methods: {
      input () {
        this.$emit('input', this.internalUser)
      },
      blur () {
        this.$emit('blur', this.internalUser)
      }
    }
  }
</script>
