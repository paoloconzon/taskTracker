<template>
  <div>
    <!-- Nessun task attivo -->
    <v-card v-if="!task" class="text-center pa-8" style="min-height:220px">
      <v-icon size="56" color="grey-lighten-1">mdi-timer-off-outline</v-icon>
      <p class="text-h6 text-medium-emphasis mt-3">Nessun task attivo</p>
      <p class="text-body-2 text-medium-emphasis">Seleziona un argomento per iniziare</p>
    </v-card>

    <!-- Task attivo -->
    <v-card v-else :style="{ borderLeft: `5px solid ${task.argomento_colore || '#1565C0'}` }">
      <v-card-title class="pa-4 pb-2 d-flex align-center">
        <v-icon :color="task.argomento_colore" class="mr-2">
          {{ task.argomento_icona || 'mdi-clock' }}
        </v-icon>
        <span class="text-truncate">{{ task.argomento_nome }}</span>
        <v-spacer />
        <!-- Timer -->
        <v-chip color="success" variant="elevated" size="small">
          <v-icon start size="14">mdi-timer</v-icon>
          {{ durataStr }}
        </v-chip>
      </v-card-title>

      <v-card-text class="pa-4 pt-2">
        <!-- Argomento (combo albero) -->
        <ArgomentoSelect
          v-model="form.id_argomento"
          label="Argomento"
          class="mb-3"
        />

        <!-- Azione -->
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

        <!-- Descrizione task -->
        <v-text-field
          v-model="form.descrizione"
          label="Descrizione task"
          variant="outlined"
          density="compact"
          clearable
          class="mb-3"
        />

        <!-- Note log -->
        <v-textarea
          v-model="form.note"
          label="Note log corrente"
          variant="outlined"
          density="compact"
          rows="2"
          clearable
          class="mb-3"
        />

        <!-- Flag -->
        <v-row dense>
          <v-col cols="4">
            <v-text-field v-model="form.flag1" label="Flag 1" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="4">
            <v-text-field v-model="form.flag2" label="Flag 2" variant="outlined" density="compact" />
          </v-col>
          <v-col cols="4">
            <v-text-field v-model="form.flag3" label="Flag 3" variant="outlined" density="compact" />
          </v-col>
        </v-row>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-3 flex-wrap gap-2">
        <v-btn
          color="primary"
          variant="elevated"
          size="small"
          :loading="saving"
          @click="salva"
        >
          <v-icon start>mdi-content-save</v-icon> Salva
        </v-btn>
        <v-spacer />
        <v-btn
          color="warning"
          variant="tonal"
          size="small"
          :loading="pausing"
          @click="mettInPausa"
        >
          <v-icon start>mdi-pause</v-icon> Pausa
        </v-btn>
        <v-btn
          color="error"
          variant="tonal"
          size="small"
          :loading="closing"
          @click="chiudiTask"
        >
          <v-icon start>mdi-stop</v-icon> Chiudi
        </v-btn>
      </v-card-actions>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useTaskStore }   from '../stores/task.js'
import { apiGetAzioni, apiPutTask, apiPutTaskLog, apiPausa, apiChiudiTask, apiGetTaskAttivo } from '../api/index.js'
import ArgomentoSelect    from './ArgomentoSelect.vue'
import dayjs              from 'dayjs'

const emit    = defineEmits(['task-changed'])
const store   = useTaskStore()
const task    = computed(() => store.taskAttivo)
const azioni  = ref([])
const saving  = ref(false)
const pausing = ref(false)
const closing = ref(false)

const form = ref({
  id_argomento: null,
  id_azione:    null,
  descrizione:  '',
  note:         '',
  flag1: '', flag2: '', flag3: '',
})

// Sync form quando cambia il task
watch(task, (t) => {
  if (t) {
    form.value = {
      id_argomento: t.id_argomento,
      id_azione:    t.id_azione    || null,
      descrizione:  t.descrizione  || '',
      note:         t.log_note     || '',
      flag1: t.flag1 || '', flag2: t.flag2 || '', flag3: t.flag3 || '',
    }
  }
}, { immediate: true })

const durataStr = computed(() => {
  store.timerTick  // forza recompute ogni secondo
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
      descrizione:  form.value.descrizione,
      flag1: form.value.flag1,
      flag2: form.value.flag2,
      flag3: form.value.flag3,
      se_chiuso: 0,
    })
    // Aggiorna anche il log corrente (note)
    if (task.value.log_id) {
      await apiPutTaskLog({
        id:             task.value.log_id,
        id_task:        task.value.id,
        descrizione:    form.value.descrizione,
        data_ora_inizio: task.value.log_inizio,
        note:           form.value.note,
      })
    }
    window.$notify('Salvato!', 'success')
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
    emit('task-changed')
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
    emit('task-changed')
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
