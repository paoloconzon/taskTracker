<template>
  <v-container fluid class="pa-4">
    <v-card>
      <v-card-title class="pa-4 pb-2 d-flex align-center flex-wrap gap-2">
        <v-icon class="mr-2">mdi-table-clock</v-icon>
        Log Attività
        <v-spacer />
        <v-btn color="success" size="small" :disabled="righeFiltrate.length === 0" @click="esportaExcel" class="mr-2">
          <v-icon start>mdi-microsoft-excel</v-icon>Excel
        </v-btn>
        <v-btn color="primary" size="small" :loading="loading" @click="load">
          <v-icon start>mdi-refresh</v-icon>Aggiorna
        </v-btn>
      </v-card-title>

      <!-- Filtri -->
      <v-card-text class="pb-0">
        <v-row dense align="center">
          <v-col cols="6" sm="3">
            <v-text-field
              v-model="filtro.daData"
              label="Da data"
              type="date"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="6" sm="3">
            <v-text-field
              v-model="filtro.aData"
              label="A data"
              type="date"
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12" sm="4">
            <v-text-field
              v-model="filtro.testo"
              label="Cerca..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              @keyup.enter="load"
            />
          </v-col>
          <v-col cols="12" sm="2" class="d-flex align-center gap-2">
            <v-switch
              v-model="mostraPausa"
              label="Pause"
              hide-details
              density="compact"
            />
            <v-btn color="primary" @click="load">Filtra</v-btn>
          </v-col>
        </v-row>
      </v-card-text>

      <!-- Griglia -->
      <v-data-table
        :headers="headers"
        :items="righeFiltrate"
        :loading="loading"
        density="compact"
        hover
        :items-per-page="50"
        class="log-table"
        @click:row="(_, { item }) => apriEdit(item)"
      >
        <template #item.utente_descrizione="{ item }">
          <span class="text-caption">{{ item.utente_descrizione }}</span>
        </template>

        <template #item.argomento="{ item }">
          <div class="d-flex align-center gap-1">
            <v-icon :color="item.argomento_colore" size="16">
              {{ item.argomento_icona || 'mdi-folder' }}
            </v-icon>
            <span :class="{ 'text-grey': item.se_pausa }">
              {{ item.argomento_path || item.argomento_nome }}
            </span>
            <v-chip v-if="item.se_pausa" size="x-small" color="grey">pausa</v-chip>
          </div>
        </template>

        <template #item.data_ora_inizio="{ item }">
          <span class="text-caption">{{ formatDatetime(item.data_ora_inizio) }}</span>
        </template>

        <template #item.durata="{ item }">
          <v-chip size="x-small" :color="item.se_pausa ? 'grey' : 'primary'" variant="tonal">
            {{ formatDurata(item.secondi) }}
          </v-chip>
        </template>

        <template #item.azione_nome="{ item }">
          <v-chip v-if="item.azione_nome" size="x-small" variant="tonal">
            {{ item.azione_nome }}
          </v-chip>
        </template>

        <template #item.descrizione="{ item }">
          <span class="text-caption">{{ item.descrizione }}</span>
          <div v-if="item.note" class="text-caption text-medium-emphasis font-italic">
            📝 {{ item.note }}
          </div>
        </template>

        <template #item.data_ora_fine="{ item }">
          <span v-if="item.data_ora_fine" class="text-caption">
            {{ formatDatetime(item.data_ora_fine) }}
          </span>
          <v-chip v-else size="x-small" color="success" variant="elevated">in corso</v-chip>
        </template>

        <!-- Footer con totale (escluse pause) -->
        <template #bottom>
          <div class="pa-3 d-flex justify-end align-center gap-4 bg-surface-variant">
            <span class="text-body-2">{{ righeFiltrate.length }} righe</span>
            <v-chip color="primary" variant="elevated" size="small">
              <v-icon start size="14">mdi-sigma</v-icon>
              {{ formatDurata(totaleSecondi) }}
            </v-chip>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Dialog modifica task log -->
    <v-dialog v-model="dialog" max-width="520" persistent>
      <v-card v-if="editForm">
        <v-card-title class="pa-4 d-flex align-center">
          <v-icon class="mr-2" :color="editForm.argomento_colore">
            {{ editForm.argomento_icona || 'mdi-folder' }}
          </v-icon>
          <span class="text-truncate">{{ editForm.argomento_path || editForm.argomento_nome }}</span>
        </v-card-title>

        <v-card-text class="pa-4 pt-2">
          <v-row dense>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="editForm.inizio_input"
                label="Inizio"
                type="datetime-local"
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="editForm.fine_input"
                label="Fine"
                type="datetime-local"
                variant="outlined"
                density="compact"
                clearable
              />
            </v-col>
          </v-row>

          <v-text-field
            v-model="editForm.descrizione"
            label="Descrizione"
            variant="outlined"
            density="compact"
            clearable
            class="mb-3"
          />

          <v-textarea
            v-model="editForm.note"
            label="Note"
            variant="outlined"
            density="compact"
            rows="3"
            clearable
          />
        </v-card-text>

        <v-card-actions class="pa-4 pt-0">
          <v-btn color="error" variant="text" :loading="deleting" @click="elimina">
            <v-icon start>mdi-delete</v-icon>Elimina
          </v-btn>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Annulla</v-btn>
          <v-btn color="primary" :loading="saving" @click="salva">
            <v-icon start>mdi-content-save</v-icon>Salva
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { apiGetTaskLog, apiPutTaskLog, apiDelTaskLog } from '../api/index.js'
import { useSessionStore } from '../stores/session.js'
import dayjs               from 'dayjs'
import * as XLSX           from 'xlsx'

const sess    = useSessionStore()
const loading = ref(false)
const saving  = ref(false)
const deleting = ref(false)
const righe   = ref([])
const totaleSecondi = ref(0)
const dialog  = ref(false)
const editForm = ref(null)

const today      = dayjs().format('YYYY-MM-DD')
const filtro     = ref({ daData: today, aData: today, testo: '' })
const mostraPausa = ref(false)

const righeFiltrate = computed(() =>
  mostraPausa.value ? righe.value : righe.value.filter(r => !r.se_pausa)
)

const headers = computed(() => {
  const cols = []
  if (sess.isAdmin()) {
    cols.push({ title: 'Utente', key: 'utente_descrizione', sortable: true, width: '130px' })
  }
  cols.push(
    { title: 'Argomento',          key: 'argomento',       sortable: true,  width: '200px' },
    { title: 'Inizio',             key: 'data_ora_inizio',  sortable: true,  width: '140px' },
    { title: 'Fine',               key: 'data_ora_fine',    sortable: false, width: '120px' },
    { title: 'Durata',             key: 'durata',           sortable: false, width: '90px'  },
    { title: 'Azione',             key: 'azione_nome',      sortable: true,  width: '120px' },
    { title: 'Descrizione / Note', key: 'descrizione',      sortable: false },
  )
  return cols
})

// Converte "YYYYMMDD HHmmss" → "YYYY-MM-DDTHH:mm" per datetime-local
function toDatetimeLocal(s) {
  if (!s) return ''
  const clean = s.replace(' ', '')
  const dt = dayjs(clean, 'YYYYMMDDHHmmss')
  return dt.isValid() ? dt.format('YYYY-MM-DDTHH:mm') : ''
}

// Converte "YYYY-MM-DDTHH:mm" → "YYYY-MM-DDTHH:mm:ss" per il backend (fromFront accetta ISO)
function fromDatetimeLocal(s) {
  if (!s) return null
  return s.length === 16 ? s + ':00' : s
}

function apriEdit(item) {
  editForm.value = {
    ...item,
    inizio_input: toDatetimeLocal(item.data_ora_inizio),
    fine_input:   toDatetimeLocal(item.data_ora_fine),
  }
  dialog.value = true
}

async function load() {
  loading.value = true
  try {
    const f = {
      daData: filtro.value.daData.replace(/-/g, ''),
      aData:  filtro.value.aData.replace(/-/g, ''),
      testo:  filtro.value.testo || undefined,
    }
    const r = await apiGetTaskLog(f)
    righe.value         = r.data.elenco
    totaleSecondi.value = r.data.totaleSecondi || 0
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    loading.value = false
  }
}

async function salva() {
  saving.value = true
  try {
    await apiPutTaskLog({
      id:             editForm.value.id,
      id_task:        editForm.value.id_task,
      descrizione:    editForm.value.descrizione || null,
      note:           editForm.value.note        || null,
      data_ora_inizio: fromDatetimeLocal(editForm.value.inizio_input),
      data_ora_fine:   fromDatetimeLocal(editForm.value.fine_input),
    })
    window.$notify('Salvato!', 'success')
    dialog.value = false
    await load()
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    saving.value = false
  }
}

async function elimina() {
  deleting.value = true
  try {
    await apiDelTaskLog(editForm.value.id)
    window.$notify('Eliminato', 'info')
    dialog.value = false
    await load()
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    deleting.value = false
  }
}

function formatDurata(sec) {
  if (!sec || sec < 0) return '-'
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  const s = sec % 60
  if (h > 0) return `${h}h ${m}m`
  if (m > 0) return `${m}m ${s}s`
  return `${s}s`
}

function formatDatetime(s) {
  if (!s) return ''
  const clean = s.replace(' ', '')
  const dt = dayjs(clean, 'YYYYMMDDHHmmss')
  return dt.isValid() ? dt.format('DD/MM HH:mm') : s
}

function esportaExcel() {
  const rows = righeFiltrate.value.map(r => {
    const dt = dayjs(r.data_ora_inizio.replace(' ', ''), 'YYYYMMDDHHmmss')

    // Scorre a sinistra: la radice va sempre in "Argomento"
    let argomento, figlio, nipote
    if (r.arg_nonno_nome) {
      argomento = r.arg_nonno_nome
      figlio    = r.arg_padre_nome || ''
      nipote    = r.argomento_nome || ''
    } else if (r.arg_padre_nome) {
      argomento = r.arg_padre_nome
      figlio    = r.argomento_nome || ''
      nipote    = ''
    } else {
      argomento = r.argomento_nome || ''
      figlio    = ''
      nipote    = ''
    }

    return {
      'Giorno':          dt.isValid() ? dt.format('DD/MM/YYYY') : '',
      'Ora inizio':      dt.isValid() ? dt.format('HH:mm') : '',
      'Tempo impiegato': formatDurata(r.secondi),
      'Argomento':       argomento,
      'Figlio':          figlio,
      'Nipote':          nipote,
      'Azione':          r.azione_nome || '',
      'Descrizione':     r.descrizione || '',
      'Flag1':           r.task_flag1  || '',
      'Flag2':           r.task_flag2  || '',
      'Note':            r.note        || '',
    }
  })

  const ws = XLSX.utils.json_to_sheet(rows)
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Log')

  const daStr = filtro.value.daData.replace(/-/g, '')
  const aStr  = filtro.value.aData.replace(/-/g, '')
  const nome  = daStr === aStr ? `log_${daStr}.xlsx` : `log_${daStr}_${aStr}.xlsx`
  XLSX.writeFile(wb, nome)
}

onMounted(load)
</script>

<style>
.log-table .v-data-table__tr { cursor: pointer; }
.log-table .v-data-table__tr:hover td { background: rgba(21,101,192,.05) !important; }
</style>
