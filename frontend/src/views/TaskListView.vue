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
        <v-text-field
          v-model="testo"
          label="Cerca..."
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="compact"
          clearable
          style="max-width:320px"
          @keyup.enter="load"
        />
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

            <!-- Riga 2: descrizione + flag1 + flag2 -->
            <div v-if="task.descrizione || task.flag1 || task.flag2" class="d-flex align-center gap-2 flex-wrap mt-1">
              <span v-if="task.descrizione" class="descrizione">{{ task.descrizione }}</span>
              <v-chip v-if="task.flag1" size="small" variant="tonal" color="deep-purple">
                {{ task.flag1 }}
              </v-chip>
              <v-chip v-if="task.flag2" size="small" variant="tonal" color="indigo">
                {{ task.flag2 }}
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
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useTaskStore }  from '../stores/task.js'
import { apiGetTask, apiRiprendi, apiChiudiTask, apiGetTaskAttivo } from '../api/index.js'
import dayjs             from 'dayjs'

const store        = useTaskStore()
const loading      = ref(false)
const tasks        = ref([])
const mostraChiusi = ref(false)
const testo        = ref('')
const riprendendo  = ref(null)
const chiudendo    = ref(null)
const ordinamento  = ref('recenti')

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
  return tasks.value // già ordinati per id DESC dal backend
})

async function load() {
  loading.value = true
  try {
    const r = await apiGetTask({
      seMostraChiusi: mostraChiusi.value,
      testo: testo.value || undefined,
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
</style>
