<template>
  <v-container fluid class="pa-4" style="max-width: 600px;">

    <!-- ===== CAMBIO PASSWORD ===== -->
    <v-card class="mb-6">
      <v-card-title class="pa-4 d-flex align-center">
        <v-icon class="mr-2">mdi-lock-reset</v-icon>
        Cambio password
      </v-card-title>

      <v-card-text class="pa-4 pt-2">
        <v-text-field
          v-model="pwd.vecchia"
          label="Password attuale"
          :type="show.vecchia ? 'text' : 'password'"
          :append-inner-icon="show.vecchia ? 'mdi-eye-off' : 'mdi-eye'"
          variant="outlined"
          density="compact"
          class="mb-3"
          @click:append-inner="show.vecchia = !show.vecchia"
        />
        <v-text-field
          v-model="pwd.nuova"
          label="Nuova password"
          :type="show.nuova ? 'text' : 'password'"
          :append-inner-icon="show.nuova ? 'mdi-eye-off' : 'mdi-eye'"
          variant="outlined"
          density="compact"
          class="mb-3"
          @click:append-inner="show.nuova = !show.nuova"
        />
        <v-text-field
          v-model="pwd.conferma"
          label="Conferma nuova password"
          :type="show.conferma ? 'text' : 'password'"
          :append-inner-icon="show.conferma ? 'mdi-eye-off' : 'mdi-eye'"
          variant="outlined"
          density="compact"
          :error-messages="erroreConferma"
          @click:append-inner="show.conferma = !show.conferma"
        />
      </v-card-text>

      <v-card-actions class="pa-4 pt-0">
        <v-spacer />
        <v-btn
          color="primary"
          :loading="savingPwd"
          :disabled="!pwdValida"
          @click="salvaPwd"
        >
          <v-icon start>mdi-content-save</v-icon>Salva password
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- ===== CONFIGURAZIONE MANTIS ===== -->
    <v-card>
      <v-card-title class="pa-4 d-flex align-center">
        <v-icon class="mr-2">mdi-bug</v-icon>
        Configurazione Mantis
      </v-card-title>

      <v-card-text class="pa-4 pt-2">
        <v-text-field
          v-model="mantis.mantis_user"
          label="Utente Mantis"
          variant="outlined"
          density="compact"
          prepend-inner-icon="mdi-account"
          class="mb-3"
        />
        <v-text-field
          v-model="mantis.mantis_pwd"
          label="Password Mantis (lascia vuoto per non modificarla)"
          :type="show.mantis_pwd ? 'text' : 'password'"
          :append-inner-icon="show.mantis_pwd ? 'mdi-eye-off' : 'mdi-eye'"
          variant="outlined"
          density="compact"
          class="mb-3"
          @click:append-inner="show.mantis_pwd = !show.mantis_pwd"
        />
        <v-text-field
          v-model="mantis.mantis_wsdl"
          label="URL WSDL Mantis"
          variant="outlined"
          density="compact"
          prepend-inner-icon="mdi-link"
          placeholder="http://mantis.example.com/api/soap/mantisconnect.php?wsdl"
        />
      </v-card-text>

      <v-card-actions class="pa-4 pt-0">
        <v-spacer />
        <v-btn
          color="primary"
          :loading="savingMantis"
          @click="salvaMantis"
        >
          <v-icon start>mdi-content-save</v-icon>Salva configurazione
        </v-btn>
      </v-card-actions>
    </v-card>

  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { apiCambioPassword, apiGetProfilo, apiSaveProfilo } from '../api/index.js'

// ---- Password ----
const pwd  = ref({ vecchia: '', nuova: '', conferma: '' })
const show = ref({ vecchia: false, nuova: false, conferma: false, mantis_pwd: false })
const savingPwd = ref(false)

const erroreConferma = computed(() =>
  pwd.value.conferma && pwd.value.nuova !== pwd.value.conferma
    ? 'Le password non coincidono' : ''
)
const pwdValida = computed(() =>
  pwd.value.vecchia &&
  pwd.value.nuova.length >= 6 &&
  pwd.value.nuova === pwd.value.conferma
)

async function salvaPwd() {
  savingPwd.value = true
  try {
    await apiCambioPassword(pwd.value.vecchia, pwd.value.nuova)
    pwd.value = { vecchia: '', nuova: '', conferma: '' }
    window.$notify('Password aggiornata!', 'success')
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    savingPwd.value = false
  }
}

// ---- Mantis ----
const mantis       = ref({ mantis_user: '', mantis_pwd: '', mantis_wsdl: '' })
const savingMantis = ref(false)

async function salvaMantis() {
  savingMantis.value = true
  try {
    await apiSaveProfilo({
      mantis_user: mantis.value.mantis_user,
      mantis_pwd:  mantis.value.mantis_pwd || undefined,
      mantis_wsdl: mantis.value.mantis_wsdl,
    })
    mantis.value.mantis_pwd = ''
    window.$notify('Configurazione Mantis salvata!', 'success')
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    savingMantis.value = false
  }
}

onMounted(async () => {
  try {
    const r = await apiGetProfilo()
    mantis.value.mantis_user = r.data.mantis_user || ''
    mantis.value.mantis_wsdl = r.data.mantis_wsdl || ''
  } catch {}
})
</script>
