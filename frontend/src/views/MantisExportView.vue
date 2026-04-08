<template>
  <v-container fluid class="pa-4">
    <v-card>
      <v-card-title class="pa-4 pb-2 d-flex align-center flex-wrap gap-2">
        <v-icon class="mr-2">mdi-bug-check</v-icon>
        Esportazione Mantis
        <v-spacer />
        <v-btn
          color="primary"
          size="small"
          variant="tonal"
          :disabled="righeFiltrate.length === 0"
          @click="selezionaTutte"
        >
          <v-icon start>mdi-checkbox-multiple-marked</v-icon>
          {{ tutteSelezionate ? 'Deseleziona tutte' : 'Seleziona tutte' }}
        </v-btn>
        <v-btn
          color="success"
          size="small"
          :disabled="selezione.length === 0"
          @click="anteprimaImport"
        >
          <v-icon start>mdi-upload</v-icon>
          Importa in Mantis ({{ selezione.length }})
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
          <v-col cols="auto">
            <v-btn color="primary" @click="load">
              <v-icon start>mdi-magnify</v-icon>Filtra
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>

      <!-- Griglia -->
      <v-data-table
        v-model="selezione"
        :headers="headers"
        :items="righeFiltrate"
        :loading="loading"
        item-value="_key"
        show-select
        density="compact"
        hover
        :items-per-page="100"
        class="mantis-table"
      >
        <!-- Argomento -->
        <template #item.argomento_nome="{ item }">
          <div class="d-flex align-center gap-1">
            <v-icon :color="item.argomento_colore" size="16">
              {{ item.argomento_icona || 'mdi-folder' }}
            </v-icon>
            <span>
              <span v-if="item.arg_padre_nome" class="text-medium-emphasis text-caption">
                {{ item.arg_padre_nome }} /
              </span>
              {{ item.argomento_nome }}
            </span>
          </div>
        </template>

        <!-- Mantis -->
        <template #item.mantis="{ item }">
          <v-chip v-if="item.mantis" size="small" variant="tonal" color="deep-purple">
            {{ item.mantis }}
          </v-chip>
        </template>

        <!-- Ticket -->
        <template #item.ticket="{ item }">
          <v-chip v-if="item.ticket" size="small" variant="tonal" color="indigo">
            {{ item.ticket }}
          </v-chip>
        </template>

        <!-- Tempo -->
        <template #item.secondi_totali="{ item }">
          <v-chip size="small" color="primary" variant="tonal">
            <v-icon start size="12">mdi-timer</v-icon>
            {{ formatDurata(item.secondi_totali) }}
          </v-chip>
        </template>

        <!-- Descrizioni (multiriga, max 5 righe) -->
        <template #item.descrizioni="{ item }">
          <div class="py-1">
            <div
              v-for="(riga, i) in (item.descrizioni || '').split('\n').filter(Boolean).slice(0, 5)"
              :key="i"
              class="text-caption"
            >
              {{ riga }}
            </div>
            <div
              v-if="(item.descrizioni || '').split('\n').filter(Boolean).length > 5"
              class="text-caption text-medium-emphasis"
            >…</div>
          </div>
        </template>

        <!-- Footer -->
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

    <!-- Dialog anteprima chiamate SOAP -->
    <v-dialog v-model="dialogAnteprima" max-width="700" scrollable>
      <v-card>
        <v-card-title class="pa-4 d-flex align-center">
          <v-icon class="mr-2" color="primary">mdi-eye</v-icon>
          Anteprima chiamate SOAP — mc_issue_add
        </v-card-title>

        <v-card-text class="pa-4 pt-0">
          <v-alert type="info" variant="tonal" class="mb-4" density="compact">
            Queste chiamate verranno inviate a Mantis. Nessun dato è ancora stato inviato.
          </v-alert>

          <v-expansion-panels variant="accordion">
            <v-expansion-panel
              v-for="chiamata in anteprimaChiamate"
              :key="chiamata.mantisId"
            >
              <v-expansion-panel-title>
                <div class="d-flex align-center gap-3">
                  <v-chip size="small" color="deep-purple" variant="tonal">
                    Mantis #{{ chiamata.mantisId }}
                  </v-chip>
                  <span class="text-caption text-medium-emphasis">
                    {{ chiamata.righe }} riga/e — {{ chiamata.tempo }}
                  </span>
                </div>
              </v-expansion-panel-title>
              <v-expansion-panel-text>
                <div class="text-caption text-medium-emphasis mb-1">
                  SOAP <strong>mc_issue_note_add</strong> → issue <strong>#{{ chiamata.mantisId }}</strong> — {{ chiamata.tempo }}
                </div>
                <pre class="nota-text">{{ chiamata.xml }}</pre>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-card-text>

        <v-card-actions class="pa-4 pt-0">
          <v-btn variant="text" @click="dialogAnteprima = false">Annulla</v-btn>
          <v-spacer />
          <v-btn color="success" :loading="importing" @click="importaMantis">
            <v-icon start>mdi-send</v-icon>Conferma e invia
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <!-- Dialog risultati invio -->
    <v-dialog v-model="dialogRisultati" max-width="800" scrollable>
      <v-card>
        <v-card-title class="pa-4 d-flex align-center">
          <v-icon class="mr-2" color="primary">mdi-information</v-icon>
          Risultati invio SOAP
        </v-card-title>

        <v-card-text class="pa-4 pt-0">
          <v-expansion-panels variant="accordion">
            <v-expansion-panel
              v-for="res in risultatiInvio"
              :key="res.mantisId"
            >
              <v-expansion-panel-title>
                <div class="d-flex align-center gap-3">
                  <v-icon :color="res.ok ? 'success' : 'error'" size="18">
                    {{ res.ok ? 'mdi-check-circle' : 'mdi-alert-circle' }}
                  </v-icon>
                  <v-chip size="small" color="deep-purple" variant="tonal">
                    Mantis #{{ res.mantisId }}
                  </v-chip>
                  <span v-if="res.ok" class="text-caption text-success">
                    OK — note_id {{ res.note_id }}
                  </span>
                  <span v-else class="text-caption text-error">
                    {{ res.errore || `HTTP ${res.httpCode}` }}
                  </span>
                </div>
              </v-expansion-panel-title>
              <v-expansion-panel-text>
                <div class="mb-2">
                  <strong class="text-caption">Endpoint:</strong>
                  <code class="text-caption ml-1">{{ res.endpoint }}</code>
                </div>
                <div class="mb-2">
                  <strong class="text-caption">HTTP Status:</strong>
                  <v-chip size="x-small" :color="res.httpCode >= 200 && res.httpCode < 300 ? 'success' : 'error'" class="ml-1">
                    {{ res.httpCode }}
                  </v-chip>
                </div>
                <v-divider class="mb-2" />
                <div class="text-caption text-medium-emphasis mb-1"><strong>Request XML inviato:</strong></div>
                <pre class="nota-text mb-3">{{ res.xml }}</pre>
                <div class="text-caption text-medium-emphasis mb-1"><strong>Response Mantis:</strong></div>
                <pre class="nota-text">{{ res.response || '(nessuna risposta)' }}</pre>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-card-text>

        <v-card-actions class="pa-4 pt-0">
          <v-spacer />
          <v-btn color="primary" @click="dialogRisultati = false">Chiudi</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { apiGetMantisExport, apiMantisImport } from '../api/index.js'
import dayjs from 'dayjs'

const loading          = ref(false)
const importing        = ref(false)
const righe            = ref([])
const selezione        = ref([])
const dialogAnteprima  = ref(false)
const anteprimaChiamate = ref([])  // [{mantisId, xml, righe, tempo}]
const righePerInvio    = ref([])   // righe già validate, pronte per l'invio
const dialogRisultati  = ref(false)
const risultatiInvio   = ref([])   // [{mantisId, ok, endpoint, httpCode, response, errore}]

const today   = dayjs().format('YYYY-MM-DD')
const filtro  = ref({
  daData: dayjs().subtract(30, 'day').format('YYYY-MM-DD'),
  aData:  today,
})

// Aggiunge chiave univoca per v-data-table
const righeFiltrate = computed(() =>
  righe.value.map(r => ({
    ...r,
    _key: `${r.giorno}_${r.id_task}`,
  }))
)

const totaleSecondi = computed(() =>
  righeFiltrate.value.reduce((s, r) => s + (parseInt(r.secondi_totali) || 0), 0)
)

const tutteSelezionate = computed(() =>
  righeFiltrate.value.length > 0 &&
  selezione.value.length === righeFiltrate.value.length
)

const headers = [
  { title: 'Giorno',      key: 'giorno',          sortable: true,  width: '100px' },
  { title: 'Argomento',   key: 'argomento_nome',   sortable: true              },
  { title: 'Mantis',      key: 'mantis',           sortable: true,  width: '120px' },
  { title: 'Ticket',      key: 'ticket',           sortable: true,  width: '120px' },
  { title: 'Tempo',       key: 'secondi_totali',   sortable: true,  width: '100px' },
  { title: 'Descrizioni', key: 'descrizioni',      sortable: false              },
]

async function load() {
  loading.value = true
  selezione.value = []
  try {
    const f = {
      daData: filtro.value.daData.replace(/-/g, ''),
      aData:  filtro.value.aData.replace(/-/g, ''),
    }
    const r = await apiGetMantisExport(f)
    righe.value = r.data.elenco
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    loading.value = false
  }
}

function selezionaTutte() {
  if (tutteSelezionate.value) {
    selezione.value = []
  } else {
    selezione.value = righeFiltrate.value.map(r => r._key)
  }
}

function formatDurataMin(sec) {
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  return h > 0 ? `${h}h ${m}m` : `${m}m`
}

function escXml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;')
}

function buildSoapXml(mantisId, rows) {
  const secondiTot = rows.reduce((s, r) => s + (parseInt(r.secondi_totali) || 0), 0)
  const minuti     = Math.round(secondiTot / 60)
  const blocchi    = rows.map(r => {
    const giorno = dayjs(r.giorno).format('DD/MM/YY')
    const tempo  = formatDurataMin(parseInt(r.secondi_totali) || 0)
    const riga2  = (r.ticket?.trim()
      ? `ticket n.${r.ticket.trim()}`
      : `attivita n.${r.id_task}`) + ` - tempo ${tempo}`
    const desc   = (r.descrizioni || '').trim()
    return `attività giorno ${giorno}\n${riga2}\n${desc}`
  })
  const testo = blocchi.join('\n\n')

  return `<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                  xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:man="http://futureware.biz/mantisconnect">
  <soapenv:Header/>
  <soapenv:Body>
    <man:mc_issue_note_add soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
      <username xsi:type="xsd:string">[mantis_user]</username>
      <password xsi:type="xsd:string">[mantis_pwd]</password>
      <issue_id xsi:type="xsd:integer">${escXml(mantisId)}</issue_id>
      <note xsi:type="man:IssueNoteData">
        <text xsi:type="xsd:string">${escXml(testo)}</text>
        <time_tracking xsi:type="xsd:integer">${minuti}</time_tracking>
      </note>
    </man:mc_issue_note_add>
  </soapenv:Body>
</soapenv:Envelope>`
}

function anteprimaImport() {
  const selezionate = righeFiltrate.value
    .filter(r => selezione.value.includes(r._key) && r.mantis?.trim())

  if (!selezionate.length) {
    window.$notify('Nessuna riga selezionata con il campo Mantis valorizzato', 'warning')
    return
  }

  // Raggruppa per mantis
  const gruppi = {}
  for (const r of selezionate) {
    const id = r.mantis.trim()
    if (!gruppi[id]) gruppi[id] = []
    gruppi[id].push(r)
  }

  anteprimaChiamate.value = Object.entries(gruppi).map(([mantisId, rows]) => {
    const secondiTot = rows.reduce((s, r) => s + (parseInt(r.secondi_totali) || 0), 0)
    return {
      mantisId,
      xml:   buildSoapXml(mantisId, rows),
      tempo: formatDurataMin(secondiTot),
      righe: rows.length,
    }
  })

  righePerInvio.value   = selezionate
  dialogAnteprima.value = true
}

async function importaMantis() {
  importing.value = true
  try {
    const r = await apiMantisImport(righePerInvio.value)
    dialogAnteprima.value = false

    const entries = Object.entries(r.data.risultati)
    risultatiInvio.value = entries.map(([mantisId, v]) => ({ mantisId, ...v }))
    dialogRisultati.value = true

    const ok  = entries.filter(([, v]) => v.ok).length
    const err = entries.filter(([, v]) => !v.ok).length
    if (err === 0) {
      window.$notify(`Inserite ${ok} note in Mantis`, 'success')
    } else {
      window.$notify(`${ok} ok, ${err} errori — vedi dettagli`, 'warning')
    }
    await load()
  } catch (e) {
    window.$notify(e.message, 'error')
  } finally {
    importing.value = false
  }
}

function formatDurata(sec) {
  if (!sec || sec < 0) return '-'
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  if (h > 0) return `${h}h ${m}m`
  return `${m}m`
}

onMounted(load)
</script>

<style scoped>
.mantis-table :deep(.v-data-table__tr) { vertical-align: top; }
.nota-text {
  font-family: monospace;
  font-size: .8rem;
  white-space: pre-wrap;
  background: rgba(0,0,0,.04);
  border-radius: 4px;
  padding: 10px;
  line-height: 1.6;
}
</style>
