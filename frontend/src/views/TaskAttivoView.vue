<template>
  <v-container fluid class="pa-4">

    <!-- Nessun task attivo -->
    <v-card v-if="!task" class="text-center pa-10">
      <v-icon size="64" color="grey-lighten-1">mdi-timer-off-outline</v-icon>
      <p class="text-h6 text-medium-emphasis mt-3">Nessun task attivo</p>
      <p class="text-body-2 text-medium-emphasis mb-6">Seleziona un argomento dalla dashboard per iniziare</p>
      <v-btn color="primary" prepend-icon="mdi-home" @click="router.push('/')">Vai alla dashboard</v-btn>
    </v-card>

    <!-- Task attivo -->
    <v-card v-else :style="{ borderTop: `4px solid ${task.argomento_colore || '#1565C0'}` }">
      <v-card-title class="pa-4 pb-2 d-flex align-center">
        <v-icon :color="task.argomento_colore" class="mr-2">
          {{ task.argomento_icona || 'mdi-clock' }}
        </v-icon>
        <span>{{ task.argomento_nome }}</span>
        <v-spacer />
        <v-chip color="success" variant="elevated">
          <v-icon start size="16">mdi-timer</v-icon>
          {{ durataStr }}
        </v-chip>
      </v-card-title>

      <v-card-text class="pa-4 pt-2">
        <v-row>
          <v-col cols="12" md="6">
            <ArgomentoSelect v-model="form.id_argomento" label="Argomento" class="mb-3" />
          </v-col>
          <v-col cols="12" md="6">
            <v-select
              v-model="form.id_azione"
              :items="azioni"
              item-title="nome"
              item-value="id"
              label="Azione"
              variant="outlined"
              density="compact"
              clearable
              class="mb-3"
            />
          </v-col>
        </v-row>

        <!-- Descrizione log -->
        <v-textarea
          v-model="form.descrizione"
          label="Descrizione log"
          variant="outlined"
          density="compact"
          rows="3"
          clearable
          class="mb-3"
        />

        <!-- Note log -->
        <v-textarea
          v-model="form.note"
          label="Note log corrente"
          variant="outlined"
          density="compact"
          rows="4"
          clearable
          class="mb-3"
        />

        <!-- Flag -->
        <v-row dense>
          <v-col cols="4">
            <v-text-field v-model="form.mantis" label="Mantis" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="4">
            <v-text-field v-model="form.ticket" label="Ticket" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="4">
            <v-text-field v-model="form.tags" label="Tags" variant="outlined" density="compact" />
          </v-col>
        </v-row>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4">
        <v-btn color="primary" variant="elevated" :loading="saving" @click="salva">
          <v-icon start>mdi-content-save</v-icon> Salva
        </v-btn>
        <v-spacer />
        <v-btn color="warning" variant="tonal" :loading="pausing" @click="mettInPausa">
          <v-icon start>mdi-pause</v-icon> Pausa
        </v-btn>
        <v-btn color="error" variant="tonal" :loading="closing" @click="chiudiTask">
          <v-icon start>mdi-stop</v-icon> Chiudi task
        </v-btn>
      </v-card-actions>
    </v-card>

  </v-container>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter }       from 'vue-router'
import { useTaskStore }    from '../stores/task.js'
import { apiGetAzioni, apiPutTask, apiPutTaskLog, apiPausa, apiChiudiTask, apiGetTaskAttivo } from '../api/index.js'
import ArgomentoSelect     from '../components/ArgomentoSelect.vue'
import dayjs               from 'dayjs'

const router   = useRouter()
const store    = useTaskStore()
const task     = computed(() => store.taskAttivo)
const azioni   = ref([])
const saving   = ref(false)
const pausing  = ref(false)
const closing  = ref(false)

const form = ref({
  id_argomento: null,
  id_azione:    null,
  descrizione:  '',
  note:         '',
  mantis: '', ticket: '', tags: '',
})

watch(task, (t) => {
  if (t) {
    form.value = {
      id_argomento: t.id_argomento,
      id_azione:    t.id_azione    || null,
      descrizione:  t.log_descrizione || '',
      note:         t.log_note     || '',
      mantis: t.mantis || '', ticket: t.ticket || '', tags: t.tags || '',
    }
  }
}, { immediate: true })

const durataStr = computed(() => {
  store.timerTick
  if (!task.value?.log_inizio) return '0s'
  const s  = task.value.log_inizio.replace(' ', '')
  const dt = dayjs(s, 'YYYYMMDDHHmmss')
  const sec = dayjs().diff(dt, 'second')
  return store.formatDurata(sec)
})

async function salva() {
  saving.value = true
  try {
    await apiPutTask({
      id:           task.value.id,
      id_argomento: form.value.id_argomento,
      id_azione:    form.value.id_azione,
      mantis: form.value.mantis,
      ticket: form.value.ticket,
      tags: form.value.tags,
      se_chiuso: 0,
    })
    if (task.value.log_id) {
      await apiPutTaskLog({
        id:              task.value.log_id,
        id_task:         task.value.id,
        descrizione:     form.value.descrizione,
        data_ora_inizio: task.value.log_inizio,
        note:            form.value.note,
      })
    }
    const r = await apiGetTaskAttivo()
    store.setTaskAttivo(r.data.task)
    window.$notify('Salvato!', 'success')
    router.push('/')
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    saving.value = false
  }
}

async function mettInPausa() {
  pausing.value = true
  try {
    await apiPausa()
    const r = await apiGetTaskAttivo()
    store.setTaskAttivo(r.data.task)
    window.$notify('Pausa avviata', 'warning')
    router.push('/')
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    pausing.value = false
  }
}

async function chiudiTask() {
  closing.value = true
  try {
    await apiChiudiTask(task.value.id)
    store.clearTask()
    window.$notify('Task chiuso', 'info')
    router.push('/')
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    closing.value = false
  }
}

onMounted(async () => {
  try {
    const r = await apiGetAzioni()
    azioni.value = r.data.elenco
  } catch {}
})
</script>
