<template>
    <v-select
            :label="label"
            :items="jobs"
            v-model="internalJob"
            item-text="active_user_description"
            chips
            autocomplete
            clearable
            @input="input"
    >
        <template slot="selection" slot-scope="data">
        <v-chip
                @input="data.parent.selectItem(data.item)"
                :selected="data.selected"
                class="chip--select-multi"
                :key="JSON.stringify(data.item)"
        >
            <user-avatar :hash-id="data.item.active_user_hash_id"
                         :alt="data.item.active_user_name"
            ></user-avatar>
            {{ data.item.fullcode }}
        </v-chip>
    </template>
        <template slot="item" slot-scope="{ item: job }">
            <v-list-tile-avatar>
                <img :src="'/user/' + job.active_user_hash_id + '/photo'">
            </v-list-tile-avatar>
            <v-list-tile-content>
                <v-list-tile-title v-html="job.fullcode"></v-list-tile-title>
                <v-list-tile-sub-title v-html="job.active_user_description"></v-list-tile-sub-title>
            </v-list-tile-content>
        </template>
    </v-select>
</template>

<script>
  import UserAvatar from '../ui/UserAvatarComponent'

  export default {
    name: 'JobsSelectComponent',
    components: {
      'user-avatar': UserAvatar
    },
    data () {
      return {
        internalJob: this.job
      }
    },
    watch: {
      job (newjob) {
        this.internalJob = newjob
      }
    },
    model: {
      prop: 'job',
      event: 'input'
    },
    props: {
      job: {
        type: Object
      },
      label: {
        type: String,
        default: 'Escolliu la plaça'
      },
      jobs: {
        type: Array,
        required: true
      }
    },
    methods: {
      input (value) {
        this.$emit('input', value)
      }
    }
  }
</script>
