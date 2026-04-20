# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Frontend development
```bash
cd frontend
npm install          # install dependencies
npm run dev          # dev server with hot-reload (proxies /api to PHP backend)
npm run build        # production build → frontend/dist/
```

### Backend setup (XAMPP)
```bash
cp backend/config.example.php backend/config.php   # then edit DB credentials
mysql -u root -p < backend/schema.sql              # create DB and tables
```

### Frontend environment
```bash
cp frontend/.env.example frontend/.env.development
# VITE_API_BASE_URL defaults to /api if not set
# VITE_BASE_PATH: use '/' for standard server, './' for XAMPP static hosting
```

## Architecture

### Request / response contract

All API calls are a **single-endpoint POST** to `backend/index.php`. Every request body:
```json
{ "func": "...", "sessionId": "...", "data": { ... } }
```
Every response:
```json
{ "result": 0, "message": "ok", "data": { ... } }
```
`result > 0` = error, `result === -1` = warning. The frontend `call()` wrapper in [frontend/src/api/index.js](frontend/src/api/index.js) throws on `result > 0`.

### Backend (PHP)

- [backend/index.php](backend/index.php) — router: reads `func`, dispatches to handlers
- [backend/handlers.php](backend/handlers.php) — all business logic (`handleGet`, `handlePut`, `handleDel`, `handleAction`, etc.)
- [backend/helpers.php](backend/helpers.php) — `ok()`, `err()`, `requireSession()`, `toFront()`, `fromFront()`
- [backend/db.php](backend/db.php) — PDO singleton via `getDB()`
- `backend/config.php` — DB constants + `ARGOMENTO_PAUSA_ID` + `SESSION_TTL_MIN` (not in repo, copy from `config.example.php`)

**Datetime format**: DB stores `Y-m-d H:i:s`; frontend sends/receives `YYYYMMDD HHmmss` (no separators). Always use `toFront()` / `fromFront()` at the boundary.

### Session handling

Sessions are rows in `WP_TT_SESSIONI`. `requireSession()` validates the `sessionId` from the payload, enforces TTL, and returns `['user', 'idUtente', 'gruppo']`. Every authenticated handler calls this first.

### Frontend (Vue 3 + Vuetify 3 + Pinia)

**Stores**
- [frontend/src/stores/session.js](frontend/src/stores/session.js) — login state, mirrored to `localStorage` (sessionId, idUtente, user, gruppo, descrizione)
- [frontend/src/stores/task.js](frontend/src/stores/task.js) — active task + live 1-second timer; `secondiCorrente` is a computed that reads `timerTick` to force re-evaluation each tick

**Routing** ([frontend/src/router/index.js](frontend/src/router/index.js)): all routes except `/login` and `/credits` redirect to login if `!isLoggedIn()` via `beforeEach`.

**Views → responsibility**
- `HomeView` — task creation and argomento browsing
- `TaskAttivoView` / `TaskAttivoPanel` — running task controls (pause, stop, resume)
- `TaskListView` — list of recent/open tasks
- `LogView` — time-log history with date filter; admins see all users
- `ArgomentiView` — CRUD for hierarchical topics (up to 3 levels: nonno/padre/figlio)
- `MantisExportView` — group logs by Mantis issue, POST via SOAP, mark rows as exported
- `ProfiloUtenteView` — Mantis credentials + password change

### Database schema (key tables)

| Table | Purpose |
|---|---|
| `WP_TT_UTENTI` | Users; stores Mantis SOAP credentials (`mantis_user`, `mantis_pwd`, `mantis_wsdl`) |
| `WP_TT_SESSIONI` | Active sessions with TTL |
| `WP_TT_ARGOMENTI` | Hierarchical topics (self-referential via `id_argomento_padre`). `se_pausa=1` marks the system PAUSE topic (id=1, never delete). `se_personale=1` means visible only to the owner. |
| `WP_TT_TASK` | Work sessions per user; `se_chiuso=1` = closed |
| `WP_TT_TASK_LOG` | Time intervals per task; open interval = `data_ora_fine IS NULL`; `se_esportato_mantis` tracks Mantis sync state |
| `WP_TT_AZIONE` | Action labels (Sviluppo, Analisi, …) |

### Pause mechanism

`ARGOMENTO_PAUSA_ID` (id=1) is a reserved system argomento with `se_pausa=1`. Pausing creates a new task under this argomento to record idle time. All task/log queries exclude argomenti where `se_pausa=1`.

### Argomento path (3-level hierarchy)

Topics display as `NONNO / PADRE / FIGLIO`. The backend resolves this with three LEFT JOINs on `WP_TT_ARGOMENTI` (recursive CTEs are avoided because MySQL handles them poorly inside scalar subqueries). The resulting `argomento_path` field is used directly on the frontend — never reconstructed client-side.

### Mantis integration

Export groups unsynced `WP_TT_TASK_LOG` rows by Mantis ID (stored on the task), builds a SOAP XML envelope via `buildMantisXml()`, sends it via cURL to the user's `mantis_wsdl` endpoint, then sets `se_esportato_mantis=1` on successfully exported rows.
