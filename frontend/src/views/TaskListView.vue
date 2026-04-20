<template>
  <v-container fluid class="pa-4">
    <v-card>
      <v-card-title class="pa-4 pb-2 d-flex align-center flex-wrap gap-2">
        <v-icon class="mr-2">mdi-format-list-checks</v-icon>
        Task Recenti
        <v-spacer />
        <v-switch
          v-model="mostraChiusi"
          label="Mostra chiusi"
          hide-details
          density="compact"
          class="mr-2"
          @update:model-value="load"
        />
        <v-btn-toggle v-model="ordinamento" mandatory density="compact" variant="outlined" class="mr-2">
          <v-btn value="recenti" size="small">
            <v-icon start size="16">mdi-clock-outline</v-icon>Recenti
          </v-btn>
          <v-btn value="argomento" size="small">
            <v-icon start size="16">mdi-sort-alphabetical-ascending</v-icon>Argomento
          </v-btn>
        </v-btn-toggle>
        <v-btn size="small" :loading="loading" @click="load">
          <v-icon start>mdi-refresh</v-icon>Aggiorna
        </v-btn>
      </v-card-title>

      <v-card-text class="pb-0">
        <v-row dense align="center">
          <v-col cols="6" sm="3">
            <v-text-field
              v-model="daData"
              label="Da data"
              type="date"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="6" sm="3">
            <v-text-field
              v-model="aData"
              label="A data"
              type="date"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="testo"
              label="Cerca..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              @keyup.enter="load"
            />
          </v-col>
          <v-col cols="auto">
            <v-btn color="primary" @click="load">Filtra</v-btn>
          </v-col>
        </v-row>
      </v-card-text>

      <v-progress-linear v-if="loading" indeterminate />

      <v-list lines="two" class="pa-2">
        <v-list-item
          v-for="task in taskOrdinati"
          :key="task.id"
          rounded="lg"
          class="mb-2 task-item"
          :class="task.se_chiuso ? 'task-chiuso' : ''"
        >
          <template #prepend>
            <v-avatar :color="task.argomento_colore || '#607D8B'" size="42" class="mr-3">
              <v-icon color="white">{{ task.argomento_icona || 'mdi-folder' }}</v-icon>
            </v-avatar>
          </template>

          <div class="task-body py-1">
            <!-- Riga 1: argomento + chips id/azione -->
            <div class="d-flex align-center gap-2 flex-wrap">
              <span class="font-weight-medium text-body-1">
                {{ task.argomento_path || task.argomento_nome }}
              </span>
              <v-chip size="x-small" variant="tonal" color="grey">#{{ task.id }}</v-chip>
              <v-chip v-if="task.azione_nome" size="x-small" variant="tonal">
                {{ task.azione_nome }}
              </v-chip>
            </div>

            <!-- Riga 2: descrizione + mantis + ticket -->
            <div v-if="task.descrizione || task.mantis || task.ticket" class="d-flex align-start gap-2 flex-wrap mt-1">
              <div v-if="task.descrizione" class="descrizione">
                <div v-for="(riga, i) in descRighe(task.descrizione)" :key="i">{{ riga }}</div>
                <span v-if="descTruncated(task.descrizione)" class="text-caption text-medium-emphasis">...</span>
              </div>
              <v-chip v-if="task.mantis" size="small" variant="tonal" color="deep-purple">
                {{ task.mantis }}
              </v-chip>
              <v-chip v-if="task.ticket" size="small" variant="tonal" color="indigo">
                {{ task.ticket }}
              </v-chip>
            </div>

            <!-- Riga 3: stato, tempo, pulsanti -->
            <div class="d-flex align-center gap-2 flex-wrap mt-2">
              <v-chip v-if="task.se_chiuso" size="x-small" color="grey" variant="tonal">chiuso</v-chip>
              <v-chip v-else-if="task.id === taskAttivoId" size="x-small" color="success" variant="elevated">
                <v-icon start>mdi-record</v-icon>Attivo
              </v-chip>
              <v-chip v-else size="x-small" color="success" variant="tonal">aperto</v-chip>

              <v-chip size="x-small" color="primary" variant="tonal">
                <v-icon start size="12">mdi-timer</v-icon>
                {{ formatDurata(task.secondi_totali) }}
              </v-chip>
              <span v-if="task.ultimo_inizio" class="text-caption text-medium-emphasis">
                ultimo: {{ formatDatetime(task.ultimo_inizio) }}
              </span>

              <v-spacer />

              <v-btn
                icon
                size="x-small"
                variant="tonal"
                title="Storia log"
                @click.stop="apriStoria(task)"
              >
                <v-icon size="16">mdi-history</v-icon>
              </v-btn>
              <v-btn
                icon
                size="x-small"
                variant="tonal"
                title="Modifica descrizione task"
                @click.stop="apriEditDescrizione(task)"
              >
                <v-icon size="16">mdi-pencil</v-icon>
              </v-btn>
              <v-btn
                v-if="task.id !== taskAttivoId"
                color="primary"
                size="small"
                variant="elevated"
                :loading="riprendendo === task.id"
                @click="riprendi(task)"
              >
                <v-icon start>mdi-play</v-icon>Riprendi
              </v-btn>
              <v-btn
                v-if="!task.se_chiuso && task.id !== taskAttivoId"
                color="warning"
                size="small"
                variant="elevated"
                :loading="chiudendo === task.id"
                @click="chiudi(task)"
              >
                <v-icon start>mdi-check</v-icon>Chiudi
              </v-btn>
            </div>
          </div>
        </v-list-item>

        <v-list-item v-if="!loading && tasks.length === 0">
          <v-alert type="info" variant="tonal">Nessun task trovato</v-alert>
        </v-list-item>
      </v-list>
    </v-card>
  </v-container>

  <!-- Dialog storia log task -->
  <v-dialog v-model="storiaDialog" max-width="620" scrollable>
    <v-card v-if="storiaTask">
      <v-card-title class="pa-4 pb-2 d-flex align-center gap-2">
        <v-icon>mdi-history</v-icon>
        <span class="text-truncate">{{ storiaTask.argomento_path || storiaTask.argomento_nome }}</span>
        <v-chip size="x-small" variant="tonal" color="grey">#{{ storiaTask.id }}</v-chip>
        <v-spacer />
        <v-btn icon size="small" variant="text" @click="storiaDialog = false">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-3" style="max-height: 520px; overflow-y: auto;">
        <div v-if="storiaLoading" class="d-flex justify-center pa-6">
          <v-progress-circular indeterminate />
        </div>
        <div v-else-if="storiaLogs.length === 0" class="text-center text-medium-emphasis pa-6">
          Nessun log trovato
        </div>
        <div v-else>
          <div
            v-for="log in storiaLogs"
            :key="log.id"
            class="storia-item mb-3 pa-3 rounded"
          >
            <div class="d-flex align-center gap-2 mb-1">
              <v-icon size="14" color="primary">mdi-clock-outline</v-icon>
              <span class="text-caption font-weight-bold text-primary">
                {{ formatDatetime(log.data_ora_inizio) }}
                <template v-if="log.data_ora_fine"> → {{ formatDatetime(log.data_ora_fine) }}</template>
                <template v-else> <v-chip size="x-small" color="success" variant="elevated" class="ml-1">in corso</v-chip></template>
              </span>
              <v-chip size="x-small" color="primary" variant="tonal" class="ml-auto">
                {{ formatDurata(log.secondi) }}
              </v-chip>
            </div>
            <div v-if="log.descrizione" class="text-body-2 mb-1">
              <div v-for="(riga, i) in log.descrizione.split(/\r?\n/).filter(r => r.trim())" :key="i">
                {{ riga }}
              </div>
            </div>
            <div v-if="log.note" class="storia-note text-caption mt-1">
              <div v-for="(riga, i) in log.note.split(/\r?\n/).filter(r => r.trim())" :key="i">
                {{ riga }}
              </div>
            </div>
          </div>
        </div>
      </v-card-text>
    </v-card>
  </v-dialog>

  <!-- Dialog modifica descrizione task -->
  <v-dialog v-model="editDialog" max-width="520">
    <v-card>
      <v-card-title class="pa-4 pb-2">
        <v-icon start>mdi-pencil</v-icon>
        Modifica descrizione task #{{ editingTask?.id }}
      </v-card-title>
      <v-card-text>
        <v-textarea
          v-model="editingDescrizione"
          label="Descrizione"
          variant="outlined"
          rows="4"
          autofocus
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn @click="editDialog = false">Annulla</v-btn>
        <v-btn color="primary" variant="elevated" :loading="salvandoDescrizione" @click="salvaDescrizione">
          Salva
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useTaskStore }  from '../stores/task.js'
import { apiGetTask, apiRiprendi, apiChiudiTask, apiGetTaskAttivo, apiPutTask, apiGetTaskLog } from '../api/index.js'
import dayjs             from 'dayjs'

const store        = useTaskStore()
const loading      = ref(false)
const tasks        = ref([])
const mostraChiusi = ref(false)
const testo        = ref('')
const daData       = ref(dayjs().subtract(30, 'day').format('YYYY-MM-DD'))
const aData        = ref(dayjs().format('YYYY-MM-DD'))
const riprendendo       = ref(null)
const chiudendo         = ref(null)
const ordinamento       = ref('recenti')
const editDialog        = ref(false)
const editingTask       = ref(null)
const editingDescrizione = ref('')
const salvandoDescrizione = ref(false)
const storiaDialog      = ref(false)
const storiaTask        = ref(null)
const storiaLogs        = ref([])
const storiaLoading     = ref(false)

const taskAttivoId = computed(() => store.taskAttivo?.id || null)

const taskOrdinati = computed(() => {
  if (ordinamento.value === 'argomento') {
    return [...tasks.value].sort((a, b) => {
      const pa = (a.argomento_path || a.argomento_nome || '').toLowerCase()
      const pb = (b.argomento_path || b.argomento_nome || '').toLowerCase()
      if (pa !== pb) return pa.localeCompare(pb)
      return (b.ultimo_inizio || '').localeCompare(a.ultimo_inizio || '')
    })
  }
  // recenti: per ultimo task_log lavorato
  return [...tasks.value].sort((a, b) =>
    (b.ultimo_inizio || '').localeCompare(a.ultimo_inizio || '')
  )
})

async function load() {
  loading.value = true
  try {
    const r = await apiGetTask({
      seMostraChiusi: mostraChiusi.value,
      testo:  testo.value || undefined,
      daData: daData.value.replace(/-/g, ''),
      aData:  aData.value.replace(/-/g, ''),
    })
    tasks.value = r.data.elenco
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    loading.value = false
  }
}

async function riprendi(task) {
  riprendendo.value = task.id
  try {
    await apiRiprendi(task.id)
    const r = await apiGetTaskAttivo()
    store.setTaskAttivo(r.data.task)
    store.panelOpen = true
    window.$notify(`Task "${task.argomento_nome}" ripreso!`, 'success')
    await load()
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    riprendendo.value = null
  }
}

async function chiudi(task) {
  chiudendo.value = task.id
  try {
    await apiChiudiTask(task.id)
    window.$notify(`Task "${task.argomento_nome}" chiuso!`, 'success')
    await load()
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    chiudendo.value = null
  }
}

async function apriStoria(task) {
  storiaTask.value = task
  storiaLogs.value = []
  storiaDialog.value = true
  storiaLoading.value = true
  try {
    const r = await apiGetTaskLog({ idTask: task.id })
    storiaLogs.value = r.data.elenco
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    storiaLoading.value = false
  }
}

function apriEditDescrizione(task) {
  editingTask.value = task
  editingDescrizione.value = task.descrizione || ''
  editDialog.value = true
}

async function salvaDescrizione() {
  salvandoDescrizione.value = true
  try {
    await apiPutTask({ ...editingTask.value, descrizione: editingDescrizione.value })
    editingTask.value.descrizione = editingDescrizione.value
    editDialog.value = false
    window.$notify('Descrizione aggiornata', 'success')
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    salvandoDescrizione.value = false
  }
}

function descRighe(s) {
  if (!s) return []
  return s.split(/\r?\n/).filter(r => r.trim()).slice(0, 5)
}
function descTruncated(s) {
  if (!s) return false
  return s.split(/\r?\n/).filter(r => r.trim()).length > 5
}

function formatDurata(sec) {
  if (!sec || sec < 0) return '-'
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  if (h > 0) return `${h}h ${m}m`
  return `${m}m`
}

function formatDatetime(s) {
  if (!s) return ''
  const clean = (s || '').replace(' ', '')
  const dt = dayjs(clean, 'YYYYMMDDHHmmss')
  return dt.isValid() ? dt.format('DD/MM HH:mm') : s
}

onMounted(load)
</script>

<style scoped>
.task-item { border: 1px solid rgba(0,0,0,.07); transition: box-shadow .15s; }
.task-item:hover { box-shadow: 0 2px 12px rgba(0,0,0,.1) !important; }

.task-chiuso { background: #f5f5f5 !important; opacity: .72; }
.task-chiuso :deep(.v-avatar) { filter: grayscale(1); }

.task-body { width: 100%; }

.descrizione {
  font-size: .875rem;
  font-weight: 600;
  color: rgba(0,0,0,.87);
  border-left: 3px solid #1976d2;
  padding-left: 8px;
}

.storia-item {
  background: rgba(0,0,0,.03);
  border-left: 3px solid #1976d2;
}

.storia-note {
  font-family: 'Courier New', Courier, monospace;
  font-size: .75rem;
  color: rgba(0,0,0,.6);
  white-space: pre-wrap;
}
</style>
