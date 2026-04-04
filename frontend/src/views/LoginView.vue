<template>
  <v-container fluid class="fill-height bg-primary">
    <v-row align="center" justify="center">
      <v-col cols="12" sm="8" md="4">
        <v-card class="pa-6" elevation="8" rounded="xl">
          <div class="text-center mb-6">
            <v-icon size="56" color="primary">mdi-clock-check-outline</v-icon>
            <h1 class="text-h5 font-weight-bold mt-2">Task Tracker</h1>
            <p class="text-medium-emphasis text-body-2">Accedi per iniziare</p>
          </div>

          <v-form @submit.prevent="doLogin" :disabled="loading">
            <v-text-field
              v-model="form.user"
              label="Utente"
              prepend-inner-icon="mdi-account"
              variant="outlined"
              class="mb-3"
              autofocus
            />
            <v-text-field
              v-model="form.pwd"
              label="Password"
              prepend-inner-icon="mdi-lock"
              :type="showPwd ? 'text' : 'password'"
              :append-inner-icon="showPwd ? 'mdi-eye-off' : 'mdi-eye'"
              @click:append-inner="showPwd = !showPwd"
              variant="outlined"
              class="mb-4"
            />
            <v-alert v-if="errMsg" type="error" variant="tonal" class="mb-4" dense>
              {{ errMsg }}
            </v-alert>
            <v-btn
              type="submit"
              color="primary"
              block
              size="large"
              :loading="loading"
            >
              Accedi
            </v-btn>
          </v-form>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref }          from 'vue'
import { useRouter }    from 'vue-router'
import { useSessionStore } from '../stores/session.js'
import { useTaskStore }    from '../stores/task.js'
import { apiLogin, apiGetTaskAttivo } from '../api/index.js'

const router    = useRouter()
const sess      = useSessionStore()
const taskStore = useTaskStore()

const form    = ref({ user: '', pwd: '' })
const loading = ref(false)
const errMsg  = ref('')
const showPwd = ref(false)

async function doLogin() {
  errMsg.value = ''
  if (!form.value.user || !form.value.pwd) {
    errMsg.value = 'Inserisci utente e password'
    return
  }
  loading.value = true
  try {
    const r = await apiLogin(form.value.user, form.value.pwd)
    sess.setSession({ ...r.data, user: form.value.user })

    // Carica task attivo eventuale
    const ta = await apiGetTaskAttivo()
    taskStore.setTaskAttivo(ta.data.task)

    router.push({ name: 'Home' })
  } catch (e) {
    errMsg.value = e.message
  } finally {
    loading.value = false
  }
}
</script>
