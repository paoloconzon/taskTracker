# Task Tracker

Applicazione web per la gestione e il tracciamento del tempo sulle attività lavorative e personali.

## Funzionalità principali

- **Tracciamento attività** — avvia, metti in pausa e chiudi task con timer in tempo reale
- **Argomenti gerarchici** — organizza le attività in alberature (nonno / padre / figlio) con colori e icone personalizzate
- **Log attività** — storico completo di tutto il tempo registrato, con possibilità di modifica e cancellazione
- **Export Excel** — esporta i log in formato `.xlsx` con i campi: giorno, ora inizio, tempo impiegato, argomento, azione, descrizione, flag, note
- **Task recenti** — elenco di tutti i task con possibilità di riprenderli o chiuderli
- **Flag personalizzati** — ogni argomento e task può portare fino a 3 flag liberi (es. codici commessa, riferimenti esterni)
- **Argomenti personali** — flag `se_personale` per rendere un argomento visibile solo al proprio proprietario
- **Cambio password** — ogni utente può cambiare la propria password dal menu
- **Multi-utente** — gestione sessioni con login/logout; la vista log è condivisa per gli admin

## Stack tecnologico

| Layer | Tecnologia |
|---|---|
| Frontend | Vue 3 + Vuetify 3 + Pinia + Vue Router |
| Build | Vite |
| Backend | PHP 8+ con PDO |
| Database | MySQL / MariaDB |
| Export | SheetJS (xlsx) |
| Icone | Material Design Icons (@mdi/font) |

## Struttura del progetto

```
tasktracker/
├── backend/
│   ├── index.php          # Entry point unico (POST)
│   ├── handlers.php       # Logica per ogni operazione
│   ├── helpers.php        # Funzioni di utilità
│   ├── db.php             # Connessione PDO
│   ├── config.php         # Configurazione DB (NON nel repo)
│   ├── config.example.php # Template configurazione
│   ├── schema.sql         # Schema database completo
│   ├── seed_data.sql      # Dati di esempio (ambiente di lavoro)
│   └── seed_data_2.sql    # Dati di esempio (uso personale/demo)
└── frontend/
    ├── src/
    │   ├── views/         # Pagine principali
    │   ├── components/    # Componenti riutilizzabili
    │   ├── stores/        # Store Pinia (sessione, task attivo)
    │   ├── api/           # Client HTTP verso il backend
    │   └── router/        # Definizione delle route
    ├── .env.example       # Template variabili d'ambiente
    └── vite.config.js     # Configurazione Vite (proxy API)
```

## Installazione

### 1. Database

```sql
-- Crea il database ed esegui lo schema
mysql -u root -p < backend/schema.sql

-- Opzionale: carica dati di esempio
mysql -u root -p tasktracker < backend/seed_data_2.sql
```

### 2. Backend PHP

Copia il progetto dentro la `htdocs` di XAMPP (o la webroot del tuo server):

```bash
cp backend/config.example.php backend/config.php
```

Modifica `backend/config.php` con i tuoi parametri:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tasktracker');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Assicurati che il virtual host (o la configurazione Apache) punti la route `/api` alla cartella `backend/`.

### 3. Frontend

```bash
cd frontend
cp .env.example .env.development
npm install
npm run dev
```

Per la produzione:

```bash
npm run build
# I file compilati saranno in frontend/dist/
```

### 4. Configurazione Vite

Nel file `frontend/vite.config.js` verifica:

```js
server: {
  proxy: {
    '/api': 'http://localhost'   // adatta all'indirizzo del backend
  }
},
base: './'   // usare '/' per server standard, './' per XAMPP
```

## Credenziali default

| Campo | Valore |
|---|---|
| Utente | `admin` |
| Password | `admin123` |

> Cambia la password al primo accesso tramite il menu **Cambio password**.

## Licenza

Distribuito sotto licenza [MIT](LICENSE).

Copyright (c) 2026 Paolo Conzon

Il software viene fornito "così com'è", senza garanzie di alcun tipo. L'autore non è responsabile per eventuali danni derivanti dall'uso del software.
