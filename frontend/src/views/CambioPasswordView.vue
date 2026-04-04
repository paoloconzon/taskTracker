<template>
  <v-container fluid class="pa-4" style="max-width: 480px;">
    <v-card>
      <v-card-title class="pa-4 d-flex align-center">
        <v-icon class="mr-2">mdi-lock-reset</v-icon>
        Cambio Password
      </v-card-title>

      <v-card-text class="pa-4 pt-2">
        <v-text-field
          v-model="form.vecchia"
          label="Password attuale"
          :type="mostraVecchia ? 'text' : 'password'"
          :append-inner-icon="mostraVecchia ? 'mdi-eye-off' : 'mdi-eye'"
          variant="outlined"
          density="compact"
          class="mb-3"
          @click:append-inner="mostraVecchia = !mostraVecchia"
        />

        <v-text-field
          v-model="form.nuova"
          label="Nuova password"
          :type="mostraNuova ? 'text' : 'password'"
          :append-inner-icon="mostraNuova ? 'mdi-eye-off' : 'mdi-eye'"
          variant="outlined"
          density="compact"
          class="mb-3"
          @click:append-inner="mostraNuova = !mostraNuova"
        />

        <v-text-field
          v-model="form.conferma"
          label="Conferma nuova password"
          :type="mostraConferma ? 'text' : 'password'"
          :append-inner-icon="mostraConferma ? 'mdi-eye-off' : 'mdi-eye'"
          variant="outlined"
          density="compact"
          :error-messages="erroreConferma"
          @click:append-inner="mostraConferma = !mostraConferma"
        />
      </v-card-text>

      <v-card-actions class="pa-4 pt-0">
        <v-btn variant="text" @click="router.back()">Annulla</v-btn>
        <v-spacer />
        <v-btn color="primary" :loading="saving" :disabled="!formValido" @click="salva">
          <v-icon start>mdi-content-save</v-icon>Salva
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter }     from 'vue-router'
import { apiCambioPassword } from '../api/index.js'

const router = useRouter()
const saving = ref(false)

const form = ref({ vecchia: '', nuova: '', conferma: '' })
const mostraVecchia  = ref(false)
const mostraNuova    = ref(false)
const mostraConferma = ref(false)

const erroreConferma = computed(() =>
  form.value.conferma && form.value.nuova !== form.value.conferma
    ? 'Le password non coincidono'
    : ''
)

const formValido = computed(() =>
  form.value.vecchia &&
  form.value.nuova.length >= 6 &&
  form.value.nuova === form.value.conferma
)

async function salva() {
  saving.value = true
  try {
    await apiCambioPassword(form.value.vecchia, form.value.nuova)
    window.$notify('Password aggiornata!', 'success')
    router.push('/')
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    saving.value = false
  }
}
</script>
