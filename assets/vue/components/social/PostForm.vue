<template>
  <q-card bordered class="mb-4" flat>
    <q-card-section>
      <q-form
        class="q-gutter-md"
      >
        <q-input
          v-model="content"
          :error="v$.content.$error"
          :label="textPlaceholder"
          autogrow
        />

        <div class="row justify-between mt-0">
          <q-file
            v-model="attachment"
            :label="$t('File upload')"
            accept="image/*"
            clearable
            dense
            @change="v$.attachment.$touch()"
          >
            <template v-slot:prepend>
              <q-icon name="attach_file" />
            </template>
          </q-file>

          <q-checkbox
            v-model="isPromoted"
            v-if="allowCreatePromoted"
            :label="$t('Mark as promoted message')"
            left-label
          />

          <q-btn
            :label="$t('Post')"
            icon="send"
            @click="sendPost"
          />
        </div>
      </q-form>
    </q-card-section>
  </q-card>
</template>

<script>
import {computed, inject, onMounted, reactive, ref, toRefs, watch} from "vue";
import {useStore} from "vuex";
import {SOCIAL_TYPE_PROMOTED_MESSAGE, SOCIAL_TYPE_WALL_POST} from "./constants";
import useVuelidate from "@vuelidate/core";
import {required} from "@vuelidate/validators";
import {useI18n} from "vue-i18n";

export default {
  name: "WallPostForm",
  setup() {
    const user = inject('social-user');

    const store = useStore();
    const {t} = useI18n();

    const currentUser = store.getters['security/getUser'];
    const userIsAdmin = store.getters['security/isAdmin'];

    const postState = reactive({
      content: '',
      attachment: null,
      isPromoted: false,
      textPlaceholder: '',
    });

    function showTextPlaceholder() {
      postState.textPlaceholder = currentUser['@id'] === user.value['@id']
        ? t('What are you thinking about?')
        : t('Write something to {0}', [user.value.fullName]);
    }

    const allowCreatePromoted = ref(false);

    function showCheckboxPromoted() {
      allowCreatePromoted.value = userIsAdmin && currentUser['@id'] === user.value['@id'];
    }

    const v$ = useVuelidate({
      content: {required},
    }, postState);

    async function sendPost() {
      v$.value.$touch();

      if (v$.value.$invalid) {
        return;
      }

      const createPostPayload = {
        content: postState.content,
        type: postState.isPromoted ? SOCIAL_TYPE_PROMOTED_MESSAGE : SOCIAL_TYPE_WALL_POST,
        sender: currentUser['@id'],
        userReceiver: currentUser['@id'] === user.value['@id'] ? null : user.value['@id'],
      };

      await store.dispatch('socialpost/create', createPostPayload);

      if (postState.attachment) {
        const post = store.state.socialpost.created;
        const attachmentPayload = {
          postId: post.id,
          file: postState.attachment
        };

        await store.dispatch('messageattachment/createWithFormData', attachmentPayload);
      }

      postState.content = '';
      postState.attachment = null;
      postState.isPromoted = false;
    }

    watch(() => user.value, () => {
      showTextPlaceholder();

      showCheckboxPromoted();
    });

    onMounted(() => {
      showTextPlaceholder();

      showCheckboxPromoted();
    });

    return {
      allowCreatePromoted,
      ...toRefs(postState),
      sendPost,
      v$
    }
  }
}
</script>
