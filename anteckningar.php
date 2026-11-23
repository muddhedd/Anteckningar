<?php
/**
 * anteckningar.php
 * -----------------------------------------------------------------------
 * Anteckningar för MinSida. Kräver inloggning (samma som ll2.php).
 *
 *  - En lista med alla anteckningar: fästa överst, sedan vald sortering.
 *  - Taggmoln med antal, sökning i namn/text/taggar – går att kombinera.
 *  - Varje anteckning har egen adress: anteckningar.php?id=12
 *  - Namn, text (Markdown) och taggar. Skapad/redigerad sätts automatiskt.
 *  - Skydd mot osparade ändringar och mot att två enheter skriver över
 *    varandra (versionsnummer).
 *  - Arkivera → syns bara i arkivet. Radera går bara från arkivet.
 *  - Export av alla anteckningar som textfil: anteckningar.php?export=1
 *
 * Tabeller: se anteckningar.sql
 * -----------------------------------------------------------------------
 */

require __DIR__ . '/common.php';

const NOTES_PAGE = 'anteckningar.php';

// Sorteringsval: nyckel => [etikett, ORDER BY]
const NOTE_SORTS = [
    'redigerad'     => ['Senast redigerad', 'a.Redigerad DESC'],
    'redigerad-old' => ['Äldst redigerad',  'a.Redigerad ASC'],
    'skapad'        => ['Senast skapad',    'a.Skapad DESC'],
    'skapad-old'    => ['Först skapad',     'a.Skapad ASC'],
    'namn'          => ['Namn A–Ö',         'a.Namn ASC'],
    'namn-desc'     => ['Namn Ö–A',         'a.Namn DESC'],
];

// ============================================================
// HJÄLPFUNKTIONER FÖR ANTECKNINGAR
// ============================================================

// Rensar en taggista: trimmar, tar bort dubbletter (oavsett versaler), max 100 tecken.
function normalizeTags($raw): array {
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : explode(',', $raw);
    }
    $out = [];
    $seen = [];
    foreach ((array)$raw as $t) {
        $t = trim(preg_replace('/\s+/u', ' ', (string)$t));
        $t = mb_substr($t, 0, 100);
        $key = mb_strtolower($t);
        if ($t === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $t;
    }
    return $out;
}

// Sparar taggarna för en anteckning (ersätter de gamla).
function saveNoteTags(int $noteId, array $tags, int $uid): void {
    dbRun('DELETE FROM AnteckningTaggKoppling WHERE AnteckningID = ?', 'i', [$noteId]);
    foreach ($tags as $tag) {
        // Finns taggen redan (även med andra versaler) återanvänds den.
        dbRun(
            'INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE ID = LAST_INSERT_ID(ID)',
            'is', [$uid, $tag]
        );
        $tagId = (int)mysqli_insert_id(getDb());
        dbRun('INSERT IGNORE INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (?, ?)', 'ii', [$noteId, $tagId]);
    }
}

// Tar bort taggar som inte längre används av någon anteckning.
function cleanupTags(int $uid): void {
    dbRun(
        'DELETE t FROM AnteckningTaggar t
         LEFT JOIN AnteckningTaggKoppling k ON k.TaggID = t.ID
         WHERE t.UserID = ? AND k.TaggID IS NULL',
        'i', [$uid]
    );
}

// Taggar för flera anteckningar: [anteckningsID => [taggnamn, ...]]
function tagsForNotes(array $ids): array {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $rows = dbAll(
        "SELECT k.AnteckningID, t.Namn
         FROM AnteckningTaggKoppling k JOIN AnteckningTaggar t ON t.ID = k.TaggID
         WHERE k.AnteckningID IN ($ph)
         ORDER BY t.Namn",
        str_repeat('i', count($ids)), $ids
    );
    $map = [];
    foreach ($rows as $r) {
        $map[(int)$r['AnteckningID']][] = $r['Namn'];
    }
    return $map;
}

function getNote(int $id, int $uid): ?array {
    $note = dbOne('SELECT * FROM Anteckningar WHERE ID = ? AND UserID = ?', 'ii', [$id, $uid]);
    if ($note) {
        $note['Taggar'] = tagsForNotes([$id])[$id] ?? [];
    }
    return $note;
}

// Det som skickas till webbläsaren om en anteckning.
function noteForClient(array $n): array {
    return [
        'id'        => (int)$n['ID'],
        'namn'      => $n['Namn'],
        'text'      => $n['Text'],
        'tags'      => $n['Taggar'] ?? [],
        'fast'      => (int)$n['Fast'] === 1,
        'arkiverad' => (int)$n['Arkiverad'] === 1,
        'version'   => (int)$n['Version'],
        'skapad'    => fmtDate($n['Skapad']),
        'redigerad' => fmtDate($n['Redigerad']),
    ];
}

// Namn om fältet lämnats tomt: första raden i texten.
function autoName(string $text): string {
    foreach (preg_split('/\R/u', $text) as $line) {
        $line = trim(preg_replace('/^\s*(#{1,6}|>|[-*+]\s+\[[ xX]\]|[-*+]|\d+[.)])\s*/u', '', $line));
        $line = trim(str_replace(['**', '~~', '`'], '', $line));
        if ($line !== '') {
            return mb_strlen($line) > 60 ? rtrim(mb_substr($line, 0, 60)) . '…' : $line;
        }
    }
    return 'Namnlös anteckning';
}

// Kort textutdrag till listan, utan Markdown-tecken.
function snippet(string $text, int $len = 160): string {
    $t = preg_replace('/```.*?(```|$)/su', ' ', $text);
    $t = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $t);
    $t = preg_replace('/^\s*(#{1,6}|>|[-*+]\s+\[[ xX]\]|[-*+]|\d+[.)])\s*/mu', '', $t);
    $t = str_replace(['**', '~~', '`'], '', $t);
    $t = trim(preg_replace('/\s+/u', ' ', $t));
    return mb_strlen($t) > $len ? rtrim(mb_substr($t, 0, $len)) . '…' : $t;
}

function likeParam(string $s): string {
    return '%' . addcslashes($s, '%_\\') . '%';
}

// ============================================================
// INLOGGNING – utan inloggning visas ingenting
// ============================================================
if (!isLoggedIn()) {
    if (isset($_POST['action'])) {
        jsonResponse(['success' => false, 'error' => 'Du är inte inloggad längre. Ladda om sidan och logga in.'], 401);
    }
    renderLoginPage('Anteckningar');
    exit;
}

$uid = currentUserId();

// ============================================================
// AJAX: spara / fäst / arkivera / återställ / radera
// ============================================================
if (isset($_POST['action'])) {
    startJsonApi();
    $db = getDb();
    $action = $_POST['action'];
    $id = (int)($_POST['id'] ?? 0);

    // ---- Spara (ny eller befintlig) ----
    if ($action === 'save') {
        $text = str_replace("\r\n", "\n", (string)($_POST['text'] ?? ''));
        $namn = trim(preg_replace('/\s+/u', ' ', (string)($_POST['namn'] ?? '')));
        $tags = normalizeTags($_POST['tags'] ?? '[]');
        $fast = !empty($_POST['fast']) ? 1 : 0;

        if ($namn === '' && trim($text) === '') {
            jsonResponse(['success' => false, 'error' => 'Anteckningen är tom – skriv ett namn eller en text.']);
        }
        $maxLen = defined('MAX_TEXT_LENGTH') ? MAX_TEXT_LENGTH : 16000000;
        if (mb_strlen($text) > $maxLen) {
            jsonResponse(['success' => false, 'error' => 'Texten är för lång (max ' . number_format($maxLen, 0, ',', ' ') . ' tecken).']);
        }
        if ($namn === '') {
            $namn = autoName($text);
        }
        $namn = mb_substr($namn, 0, 255);
        $now = nowSql();

        mysqli_begin_transaction($db);

        if ($id === 0) {
            dbRun(
                'INSERT INTO Anteckningar (UserID, Namn, Text, Fast, Arkiverad, Version, Skapad, Redigerad)
                 VALUES (?, ?, ?, ?, 0, 1, ?, ?)',
                'ississ', [$uid, $namn, $text, $fast, $now, $now]
            );
            $id = (int)mysqli_insert_id($db);
        } else {
            $version = (int)($_POST['version'] ?? 0);
            $force = !empty($_POST['force']);

            if ($force) {
                $stmt = dbRun(
                    'UPDATE Anteckningar SET Namn = ?, Text = ?, Version = Version + 1, Redigerad = ?
                     WHERE ID = ? AND UserID = ?',
                    'sssii', [$namn, $text, $now, $id, $uid]
                );
            } else {
                $stmt = dbRun(
                    'UPDATE Anteckningar SET Namn = ?, Text = ?, Version = Version + 1, Redigerad = ?
                     WHERE ID = ? AND UserID = ? AND Version = ?',
                    'sssiii', [$namn, $text, $now, $id, $uid, $version]
                );
            }

            if (mysqli_stmt_affected_rows($stmt) === 0) {
                mysqli_rollback($db);
                $current = getNote($id, $uid);
                if (!$current) {
                    jsonResponse(['success' => false, 'error' => 'Anteckningen finns inte längre (raderad på en annan enhet?). Kopiera texten om du vill behålla den.']);
                }
                // Någon annan enhet har sparat en nyare version.
                jsonResponse(['success' => false, 'conflict' => true, 'server' => noteForClient($current)]);
            }
        }

        saveNoteTags($id, $tags, $uid);
        cleanupTags($uid);
        mysqli_commit($db);

        jsonResponse(['success' => true, 'note' => noteForClient(getNote($id, $uid))]);
    }

    // ---- Fäst / lossa (räknas inte som redigering) ----
    if ($action === 'pin') {
        $fast = !empty($_POST['fast']) ? 1 : 0;
        dbRun('UPDATE Anteckningar SET Fast = ? WHERE ID = ? AND UserID = ?', 'iii', [$fast, $id, $uid]);
        jsonResponse(['success' => true, 'fast' => $fast === 1]);
    }

    // ---- Arkivera / återställ ----
    if ($action === 'archive' || $action === 'unarchive') {
        $arkiv = $action === 'archive' ? 1 : 0;
        dbRun('UPDATE Anteckningar SET Arkiverad = ? WHERE ID = ? AND UserID = ?', 'iii', [$arkiv, $id, $uid]);
        jsonResponse(['success' => true]);
    }

    // ---- Radera (bara arkiverade) ----
    if ($action === 'delete') {
        $stmt = dbRun('DELETE FROM Anteckningar WHERE ID = ? AND UserID = ? AND Arkiverad = 1', 'ii', [$id, $uid]);
        if (mysqli_stmt_affected_rows($stmt) === 0) {
            jsonResponse(['success' => false, 'error' => 'Bara arkiverade anteckningar kan raderas.']);
        }
        cleanupTags($uid);
        jsonResponse(['success' => true]);
    }

    jsonResponse(['success' => false, 'error' => 'Okänd åtgärd.'], 400);
}

// ============================================================
// EXPORT – alla anteckningar som en textfil
// ============================================================
if (isset($_GET['export'])) {
    $notes = dbAll(
        'SELECT * FROM Anteckningar WHERE UserID = ? ORDER BY Arkiverad ASC, Fast DESC, Redigerad DESC',
        'i', [$uid]
    );
    $tagMap = tagsForNotes(array_column($notes, 'ID'));

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="anteckningar-' . date('Y-m-d') . '.txt"');

    echo "ANTECKNINGAR – exporterade " . date('Y-m-d H:i') . "\n";
    echo count($notes) . " anteckningar\n";
    $section = null;
    foreach ($notes as $n) {
        $s = (int)$n['Arkiverad'] === 1 ? 'ARKIV' : 'AKTIVA';
        if ($s !== $section) {
            $section = $s;
            echo "\n\n==================== $s ====================\n";
        }
        $tags = $tagMap[(int)$n['ID']] ?? [];
        echo "\n\n# " . $n['Namn'] . ((int)$n['Fast'] === 1 ? '  [fäst]' : '') . "\n";
        echo 'Taggar: ' . ($tags ? implode(', ', $tags) : '–') . "\n";
        echo 'Skapad: ' . fmtDate($n['Skapad']) . ' | Senast redigerad: ' . fmtDate($n['Redigerad']) . "\n";
        echo "----------------------------------------\n";
        echo rtrim($n['Text']) . "\n";
    }
    exit;
}

// Alla taggar (till förslagen medan man skriver)
$allTags = array_column(
    dbAll('SELECT Namn FROM AnteckningTaggar WHERE UserID = ? ORDER BY Namn', 'i', [$uid]),
    'Namn'
);

// ============================================================
// VY: EN ANTECKNING (?id=12 eller ?ny=1)
// ============================================================
if (isset($_GET['id']) || isset($_GET['ny'])) {
    $note = null;
    if (isset($_GET['id'])) {
        $note = getNote((int)$_GET['id'], $uid);
        if (!$note) {
            http_response_code(404);
        }
    }
    $data = $note ? noteForClient($note) : [
        'id' => 0, 'namn' => '', 'text' => '', 'tags' => [], 'fast' => false,
        'arkiverad' => false, 'version' => 0, 'skapad' => '', 'redigerad' => '',
    ];
    $notFound = isset($_GET['id']) && !$note;
    $backUrl = NOTES_PAGE . ($note && $data['arkiverad'] ? '?arkiv=1' : '');
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    ?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $note ? h($note['Namn']) : 'Ny anteckning' ?> – Anteckningar</title>
    <link rel="stylesheet" href="minsida.css">
</head>
<body>
<div class="page note-page">
    <?php renderNav(NOTES_PAGE); ?>

    <?php if ($notFound): ?>
        <h1>Anteckningen finns inte</h1>
        <p>Den kan ha raderats. <a href="<?= NOTES_PAGE ?>">Till alla anteckningar</a></p>
    <?php else: ?>

    <div class="editor-top">
        <a href="<?= h($backUrl) ?>" id="back-link">← Alla anteckningar</a>
        <span id="save-state" class="save-state"></span>
    </div>

    <div id="archived-banner" class="archived-banner" hidden>
        🗄 Den här anteckningen är arkiverad och syns bara i arkivet.
    </div>

    <input type="text" id="note-name" class="note-name" placeholder="Namn (lämnas tomt → första raden)" maxlength="255" autocomplete="off">

    <div class="tag-input" id="tag-box">
        <span id="tag-chips"></span>
        <input type="text" id="tag-entry" list="tag-options" placeholder="Lägg till tagg…" autocomplete="off" enterkeyhint="done">
    </div>
    <datalist id="tag-options">
        <?php foreach ($allTags as $t): ?>
            <option value="<?= h($t) ?>">
        <?php endforeach; ?>
    </datalist>

    <div class="note-tabs" role="tablist">
        <button type="button" data-tab="skriv" role="tab">Skriv</button>
        <button type="button" data-tab="visa" role="tab">Visa</button>
        <span class="md-hint">**fet** · *kursiv* · # rubrik · - lista · - [ ] att göra · [text](länk)</span>
    </div>
    <textarea id="note-text" class="note-text" placeholder="Skriv här…"></textarea>
    <div id="note-view" class="note-view md" hidden></div>

    <div class="editor-actions">
        <button type="button" id="save-btn" class="primary-btn">Spara</button>
        <label><input type="checkbox" id="note-pin"> 📌 Fäst överst</label>
        <div class="right">
            <button type="button" id="archive-btn" class="secondary-btn">🗄 Arkivera</button>
            <button type="button" id="unarchive-btn" class="secondary-btn" hidden>↩ Återställ</button>
            <button type="button" id="delete-btn" class="danger-btn" hidden>Radera</button>
        </div>
    </div>

    <p id="note-dates" class="note-dates"></p>
    <p id="editor-msg" class="popup-msg"></p>

    <?php endif; ?>
</div>

<?php if (!$notFound): ?>
<!-- Popup: någon annan enhet har sparat under tiden -->
<div id="conflict-overlay" class="popup-overlay" hidden>
    <div class="popup-box" role="dialog" aria-modal="true" aria-labelledby="conflict-title">
        <h2 id="conflict-title">Ändrad på annan enhet</h2>
        <p id="conflict-text" style="font-size:.9em"></p>
        <div class="conflict-actions">
            <button type="button" id="conflict-mine" class="primary-btn">Behåll min version (skriv över den andra)</button>
            <button type="button" id="conflict-copy" class="secondary-btn">Spara min som en ny kopia</button>
            <button type="button" id="conflict-theirs" class="secondary-btn">Hämta den andra versionen (mina ändringar försvinner)</button>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode(csrfToken()) ?>;
const ALL_TAGS = <?= json_encode($allTags, $jsonFlags) ?>;
let note = <?= json_encode($data, $jsonFlags) ?>;
</script>
<script>
// ============================================================
// Markdown → HTML (enkel, säker: all text escapas först)
// ============================================================
function esc(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function linkHtml(href, text) {
    return '<a href="' + esc(href) + '" target="_blank" rel="noopener noreferrer">' + esc(text) + '</a>';
}
function mdInline(s) {
    const slots = [];
    const keep = html => '\u0000' + (slots.push(html) - 1) + '\u0000';
    s = s.replace(/`([^`\n]+)`/g, (m, c) => keep('<code>' + esc(c) + '</code>'));
    s = s.replace(/\[([^\]\n]+)\]\(([^)\s]+)\)/g, (m, t, u) =>
        /^(https?:\/\/|mailto:)/i.test(u) ? keep(linkHtml(u, t)) : m);
    s = s.replace(/(https?:\/\/|www\.)[^\s<>"']+/gi, m => {
        let url = m, tail = '';
        const t = url.match(/[.,;:!?)\]]+$/);
        if (t) { tail = t[0]; url = url.slice(0, -tail.length); }
        return keep(linkHtml(/^www\./i.test(url) ? 'https://' + url : url, url)) + tail;
    });
    s = esc(s);
    s = s.replace(/\*\*([^*\n]+?)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/~~([^~\n]+?)~~/g, '<del>$1</del>');
    s = s.replace(/(^|[^*\w])\*([^*\s](?:[^*\n]*?[^*\s])?)\*(?!\w)/g, '$1<em>$2</em>');
    return s.replace(/\u0000(\d+)\u0000/g, (m, i) => slots[+i]);
}
function md(src) {
    const lines = String(src).replace(/\r\n?/g, '\n').split('\n');
    const listRe = /^\s*([-*+]|\d+[.)])\s+/;
    let out = '', i = 0, para = [];
    const flush = () => {
        if (para.length) { out += '<p>' + para.map(mdInline).join('<br>') + '</p>'; para = []; }
    };
    while (i < lines.length) {
        const line = lines[i];
        let m;
        if (/^\s*```/.test(line)) {
            flush();
            const buf = [];
            i++;
            while (i < lines.length && !/^\s*```/.test(lines[i])) { buf.push(lines[i]); i++; }
            i++;
            out += '<pre><code>' + esc(buf.join('\n')) + '</code></pre>';
        } else if (/^\s*$/.test(line)) {
            flush(); i++;
        } else if ((m = line.match(/^(#{1,6})\s+(.*)$/))) {
            flush();
            out += '<h' + m[1].length + '>' + mdInline(m[2]) + '</h' + m[1].length + '>';
            i++;
        } else if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(line)) {
            flush(); out += '<hr>'; i++;
        } else if (/^\s*>/.test(line)) {
            flush();
            const buf = [];
            while (i < lines.length && /^\s*>/.test(lines[i])) { buf.push(lines[i].replace(/^\s*>\s?/, '')); i++; }
            out += '<blockquote>' + md(buf.join('\n')) + '</blockquote>';
        } else if (listRe.test(line)) {
            flush();
            const tag = /^\s*\d/.test(line) ? 'ol' : 'ul';
            out += '<' + tag + '>';
            while (i < lines.length && listRe.test(lines[i])) {
                const item = lines[i].replace(listRe, '');
                const t = item.match(/^\[( |x|X)\]\s+(.*)$/);
                out += t
                    ? '<li class="task"><input type="checkbox" disabled' + (t[1] !== ' ' ? ' checked' : '') + '> ' + mdInline(t[2]) + '</li>'
                    : '<li>' + mdInline(item) + '</li>';
                i++;
            }
            out += '</' + tag + '>';
        } else {
            para.push(line); i++;
        }
    }
    flush();
    return out;
}

// ============================================================
// Redigeraren
// ============================================================
const $ = id => document.getElementById(id);
const nameEl = $('note-name'), textEl = $('note-text'), viewEl = $('note-view');
const entryEl = $('tag-entry'), stateEl = $('save-state'), msgEl = $('editor-msg');
let tags = [];
let saved = '';        // ögonblicksbild av senast sparade innehåll
let saving = false;
let pendingConflict = null;

function snapshot() {
    return JSON.stringify({ n: nameEl.value.trim(), t: textEl.value, g: tags });
}
function isDirty() { return snapshot() !== saved; }

function updateState() {
    if (isDirty()) {
        stateEl.textContent = '● Osparade ändringar';
        stateEl.classList.add('dirty');
    } else {
        stateEl.classList.remove('dirty');
        stateEl.textContent = note.id ? '✓ Sparad' : '';
    }
}

function autoGrow() {
    textEl.style.height = 'auto';
    textEl.style.height = Math.max(textEl.scrollHeight + 2, window.innerHeight * 0.5) + 'px';
}

// ---- Taggar som "chips" ----
function renderTags() {
    const box = $('tag-chips');
    box.innerHTML = '';
    tags.forEach((t, i) => {
        const chip = document.createElement('span');
        chip.className = 'tag';
        chip.textContent = t;
        const x = document.createElement('button');
        x.type = 'button';
        x.textContent = '×';
        x.setAttribute('aria-label', 'Ta bort taggen ' + t);
        x.addEventListener('click', e => { e.stopPropagation(); tags.splice(i, 1); renderTags(); updateState(); });
        chip.appendChild(x);
        box.appendChild(chip);
    });
}
function addTag(raw) {
    const t = String(raw).trim().replace(/\s+/g, ' ').slice(0, 100);
    if (!t || tags.some(x => x.toLowerCase() === t.toLowerCase())) return;
    const existing = ALL_TAGS.find(x => x.toLowerCase() === t.toLowerCase());
    tags.push(existing || t);
    renderTags();
    updateState();
}
function commitEntry() {
    entryEl.value.split(',').forEach(addTag);
    entryEl.value = '';
}
entryEl.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        commitEntry();
    } else if (e.key === 'Backspace' && entryEl.value === '' && tags.length) {
        tags.pop(); renderTags(); updateState();
    }
});
entryEl.addEventListener('input', () => {
    if (entryEl.value.includes(',')) {
        const parts = entryEl.value.split(',');
        entryEl.value = parts.pop();
        parts.forEach(addTag);
    }
});
entryEl.addEventListener('change', commitEntry);   // val ur förslagslistan / lämnar fältet
$('tag-box').addEventListener('click', () => entryEl.focus());

// ---- Flikar Skriv / Visa ----
function setTab(tab) {
    document.querySelectorAll('.note-tabs button').forEach(b =>
        b.classList.toggle('active', b.dataset.tab === tab));
    if (tab === 'visa') {
        viewEl.innerHTML = md(textEl.value);
        viewEl.classList.toggle('empty', textEl.value.trim() === '');
        viewEl.hidden = false;
        textEl.hidden = true;
    } else {
        viewEl.hidden = true;
        textEl.hidden = false;
        autoGrow();
    }
}
document.querySelectorAll('.note-tabs button').forEach(b =>
    b.addEventListener('click', () => { setTab(b.dataset.tab); if (b.dataset.tab === 'skriv') textEl.focus(); }));
viewEl.addEventListener('click', e => {
    if (e.target.closest('a')) return;              // länkar öppnas som vanligt
    if (window.getSelection().toString()) return;   // markerar text
    setTab('skriv');
    textEl.focus();
});

// ---- Fyll i formuläret ----
function loadNote(n) {
    note = n;
    nameEl.value = n.namn;
    textEl.value = n.text;
    tags = n.tags.slice();
    renderTags();
    $('note-pin').checked = n.fast;
    $('archived-banner').hidden = !n.arkiverad;
    $('archive-btn').hidden = !n.id || n.arkiverad;
    $('unarchive-btn').hidden = !n.arkiverad;
    $('delete-btn').hidden = !n.arkiverad;
    $('back-link').href = 'anteckningar.php' + (n.arkiverad ? '?arkiv=1' : '');
    $('note-dates').textContent = n.id ? 'Skapad ' + n.skapad + ' · Senast redigerad ' + n.redigerad : '';
    document.title = (n.namn || 'Ny anteckning') + ' – Anteckningar';
    saved = snapshot();
    updateState();
}

// ---- Anrop till servern ----
function post(fields) {
    const fd = new FormData();
    fd.append('csrf', CSRF);
    Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
    return fetch('anteckningar.php', { method: 'POST', body: fd }).then(r => r.json());
}

async function save(opts = {}) {
    if (saving) return false;
    commitEntry();
    saving = true;
    msgEl.textContent = '';
    stateEl.textContent = 'Sparar…';
    const wasVisa = !viewEl.hidden;
    try {
        const data = await post({
            action: 'save',
            id: opts.asCopy ? 0 : note.id,
            version: note.version,
            namn: opts.asCopy ? (nameEl.value.trim() || 'Namnlös') + ' (kopia)' : nameEl.value,
            text: textEl.value,
            tags: JSON.stringify(tags),
            fast: $('note-pin').checked ? 1 : 0,
            force: opts.force ? 1 : 0
        });
        if (data.success) {
            loadNote(data.note);
            history.replaceState(null, '', 'anteckningar.php?id=' + data.note.id);
            stateEl.textContent = '✓ Sparad ' + new Date().toLocaleTimeString('sv-SE', { hour: '2-digit', minute: '2-digit' });
            if (wasVisa) setTab('visa');
            return true;
        }
        if (data.conflict) {
            showConflict(data.server);
        } else {
            msgEl.textContent = data.error || 'Något gick fel.';
        }
        updateState();
        return false;
    } catch (err) {
        msgEl.textContent = 'Kunde inte spara (' + err.message + '). Din text finns kvar – försök igen.';
        updateState();
        return false;
    } finally {
        saving = false;
    }
}

// ---- Konflikt: ändrad på annan enhet ----
function showConflict(server) {
    pendingConflict = server;
    $('conflict-text').textContent =
        'Anteckningen sparades på en annan enhet ' + server.redigerad +
        ', efter att du öppnade den här. Vad vill du göra?';
    $('conflict-overlay').hidden = false;
}
function closeConflict() { $('conflict-overlay').hidden = true; }
$('conflict-mine').addEventListener('click', () => {
    closeConflict();
    note.version = pendingConflict.version;
    save({ force: true });
});
$('conflict-copy').addEventListener('click', () => { closeConflict(); save({ asCopy: true }); });
$('conflict-theirs').addEventListener('click', () => {
    closeConflict();
    loadNote(pendingConflict);
    history.replaceState(null, '', 'anteckningar.php?id=' + pendingConflict.id);
    setTab(textEl.value.trim() ? 'visa' : 'skriv');
});

// ---- Knappar ----
$('save-btn').addEventListener('click', () => save());

$('note-pin').addEventListener('change', async function () {
    if (!note.id) return;   // ny anteckning: följer med när den sparas
    const data = await post({ action: 'pin', id: note.id, fast: this.checked ? 1 : 0 });
    if (data.success) note.fast = data.fast;
    else { this.checked = !this.checked; msgEl.textContent = data.error || 'Något gick fel.'; }
});

$('archive-btn').addEventListener('click', async () => {
    if (isDirty() && !(await save())) return;
    const data = await post({ action: 'archive', id: note.id });
    if (data.success) { saved = snapshot(); location.href = 'anteckningar.php'; }
    else msgEl.textContent = data.error || 'Något gick fel.';
});

$('unarchive-btn').addEventListener('click', async () => {
    if (isDirty() && !(await save())) return;
    const data = await post({ action: 'unarchive', id: note.id });
    if (data.success) { note.arkiverad = false; loadNote(note); }
    else msgEl.textContent = data.error || 'Något gick fel.';
});

$('delete-btn').addEventListener('click', async () => {
    if (!confirm('Radera "' + (note.namn || 'anteckningen') + '" för alltid?\n\nDet går inte att ångra.')) return;
    const data = await post({ action: 'delete', id: note.id });
    if (data.success) { saved = snapshot(); location.href = 'anteckningar.php?arkiv=1'; }
    else msgEl.textContent = data.error || 'Något gick fel.';
});

// ---- Ändringar, Ctrl/Cmd+S och varning vid osparat ----
nameEl.addEventListener('input', updateState);
textEl.addEventListener('input', () => { autoGrow(); updateState(); });

document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        save();
    }
    if (e.key === 'Escape' && !$('conflict-overlay').hidden) closeConflict();
});

window.addEventListener('beforeunload', e => {
    if (isDirty()) { e.preventDefault(); e.returnValue = ''; }
});

$('back-link').addEventListener('click', e => {
    if (isDirty() && !confirm('Du har osparade ändringar. Lämna ändå?')) e.preventDefault();
    else saved = snapshot();
});

// ---- Start ----
loadNote(note);
if (note.id && note.text.trim() !== '') {
    setTab('visa');
} else {
    setTab('skriv');
    (note.id ? textEl : nameEl).focus();
}
</script>
<?php endif; ?>
</body>
</html>
<?php
    exit;
}

// ============================================================
// VY: LISTAN (och arkivet med ?arkiv=1)
// ============================================================
$arkiv = isset($_GET['arkiv']) ? 1 : 0;
$q     = trim((string)($_GET['q'] ?? ''));
$tagg  = trim((string)($_GET['tagg'] ?? ''));
$sort  = array_key_exists($_GET['sort'] ?? '', NOTE_SORTS) ? $_GET['sort'] : 'redigerad';

$where  = ['a.UserID = ?', 'a.Arkiverad = ?'];
$types  = 'ii';
$params = [$uid, $arkiv];

if ($tagg !== '') {
    $where[]  = 'EXISTS (SELECT 1 FROM AnteckningTaggKoppling k JOIN AnteckningTaggar t ON t.ID = k.TaggID
                         WHERE k.AnteckningID = a.ID AND t.Namn = ?)';
    $types   .= 's';
    $params[] = $tagg;
}

// Varje sökord måste finnas någonstans: i namnet, texten eller en tagg.
foreach (preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) as $word) {
    $like = likeParam($word);
    $where[] = '(a.Namn LIKE ? OR a.Text LIKE ? OR EXISTS (
                    SELECT 1 FROM AnteckningTaggKoppling k JOIN AnteckningTaggar t ON t.ID = k.TaggID
                    WHERE k.AnteckningID = a.ID AND t.Namn LIKE ?))';
    $types  .= 'sss';
    array_push($params, $like, $like, $like);
}

$order = ($arkiv ? '' : 'a.Fast DESC, ') . NOTE_SORTS[$sort][1] . ', a.ID DESC';
$notes = dbAll(
    'SELECT a.ID, a.Namn, LEFT(a.Text, 800) AS Utdrag, a.Fast, a.Skapad, a.Redigerad
     FROM Anteckningar a WHERE ' . implode(' AND ', $where) . " ORDER BY $order",
    $types, $params
);
$tagMap = tagsForNotes(array_column($notes, 'ID'));

// Taggmoln för aktuell vy (aktiva eller arkiv)
$cloud = dbAll(
    'SELECT t.Namn, COUNT(*) AS Antal
     FROM AnteckningTaggar t
     JOIN AnteckningTaggKoppling k ON k.TaggID = t.ID
     JOIN Anteckningar a ON a.ID = k.AnteckningID
     WHERE a.UserID = ? AND a.Arkiverad = ?
     GROUP BY t.ID, t.Namn
     ORDER BY t.Namn',
    'ii', [$uid, $arkiv]
);

$counts = dbOne(
    'SELECT SUM(Arkiverad = 0) AS aktiva, SUM(Arkiverad = 1) AS arkiv FROM Anteckningar WHERE UserID = ?',
    'i', [$uid]
);
$countActive  = (int)($counts['aktiva'] ?? 0);
$countArchive = (int)($counts['arkiv'] ?? 0);

// Bygger en länk till listan med ändrade parametrar (behåller övriga).
function listUrl(array $changes = []): string {
    global $arkiv, $q, $tagg, $sort;
    $p = array_merge([
        'arkiv' => $arkiv ? 1 : '',
        'q'     => $q,
        'tagg'  => $tagg,
        'sort'  => $sort !== 'redigerad' ? $sort : '',
    ], $changes);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return NOTES_PAGE . ($p ? '?' . http_build_query($p) : '');
}

$filtered = $q !== '' || $tagg !== '';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $arkiv ? 'Arkiv – ' : '' ?>Anteckningar</title>
    <link rel="stylesheet" href="minsida.css">
</head>
<body>
<div class="page">
    <?php renderNav(NOTES_PAGE); ?>

    <h1><?= $arkiv ? '🗄 Arkiv' : 'Anteckningar' ?></h1>

    <div class="top-row">
        <?php if ($arkiv): ?>
            <a href="<?= NOTES_PAGE ?>" class="secondary-btn">← Till anteckningarna</a>
        <?php else: ?>
            <a href="<?= NOTES_PAGE ?>?ny=1" class="primary-btn">+ Ny anteckning</a>
        <?php endif; ?>

        <form method="get" class="search-form">
            <?php if ($arkiv): ?><input type="hidden" name="arkiv" value="1"><?php endif; ?>
            <?php if ($tagg !== ''): ?><input type="hidden" name="tagg" value="<?= h($tagg) ?>"><?php endif; ?>
            <?php if ($sort !== 'redigerad'): ?><input type="hidden" name="sort" value="<?= h($sort) ?>"><?php endif; ?>
            <input type="search" name="q" value="<?= h($q) ?>" placeholder="Sök i namn, text och taggar…">
            <button type="submit">Sök</button>
        </form>

        <form method="get" class="sort-form">
            <?php if ($arkiv): ?><input type="hidden" name="arkiv" value="1"><?php endif; ?>
            <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= h($q) ?>"><?php endif; ?>
            <?php if ($tagg !== ''): ?><input type="hidden" name="tagg" value="<?= h($tagg) ?>"><?php endif; ?>
            <select name="sort" onchange="this.form.submit()" aria-label="Sortering">
                <?php foreach (NOTE_SORTS as $key => [$label]): ?>
                    <option value="<?= h($key) ?>"<?= $key === $sort ? ' selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="notes-links">
        <?php if ($arkiv): ?>
            <a href="<?= NOTES_PAGE ?>">Anteckningar (<?= $countActive ?>)</a>
        <?php else: ?>
            <a href="<?= NOTES_PAGE ?>?arkiv=1">🗄 Arkiv (<?= $countArchive ?>)</a>
        <?php endif; ?>
        <a href="<?= NOTES_PAGE ?>?export=1">⬇ Exportera alla som textfil</a>
    </div>

    <?php if ($cloud): ?>
        <div class="tag-cloud">
            <?php foreach ($cloud as $c): $isActive = mb_strtolower($c['Namn']) === mb_strtolower($tagg); ?>
                <a class="tag<?= $isActive ? ' active' : '' ?>"
                   href="<?= h(listUrl(['tagg' => $isActive ? '' : $c['Namn']])) ?>"><?= h($c['Namn']) ?> <span class="count">(<?= (int)$c['Antal'] ?>)</span></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($filtered): ?>
        <p class="filter-info">
            <?= count($notes) ?> <?= count($notes) === 1 ? 'anteckning' : 'anteckningar' ?>
            <?php if ($tagg !== ''): ?> med taggen <strong><?= h($tagg) ?></strong><?php endif; ?>
            <?php if ($q !== ''): ?> som matchar <strong>"<?= h($q) ?>"</strong><?php endif; ?>
            · <a href="<?= h(listUrl(['q' => '', 'tagg' => ''])) ?>">Visa alla</a>
        </p>
    <?php endif; ?>

    <div class="note-list">
        <?php if (!$notes): ?>
            <p class="empty-row">
                <?php if ($filtered): ?>Inga anteckningar matchar.
                <?php elseif ($arkiv): ?>Arkivet är tomt.
                <?php else: ?>Inga anteckningar än. Tryck på "+ Ny anteckning" för att börja.
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php foreach ($notes as $n): $nid = (int)$n['ID']; $pinned = (int)$n['Fast'] === 1; ?>
            <article class="note-card<?= $pinned && !$arkiv ? ' pinned' : '' ?>" data-id="<?= $nid ?>" data-namn="<?= h($n['Namn']) ?>">
                <a class="note-link" href="<?= NOTES_PAGE ?>?id=<?= $nid ?>">
                    <h3><?= h($n['Namn']) ?></h3>
                    <?php $sn = snippet($n['Utdrag']); if ($sn !== ''): ?>
                        <p class="note-snippet"><?= h($sn) ?></p>
                    <?php endif; ?>
                </a>
                <?php if (!$arkiv): ?>
                    <button type="button" class="pin-btn<?= $pinned ? ' on' : '' ?>"
                            data-fast="<?= $pinned ? 1 : 0 ?>"
                            title="<?= $pinned ? 'Lossa' : 'Fäst överst' ?>"
                            aria-label="<?= $pinned ? 'Lossa' : 'Fäst överst' ?>">📌</button>
                <?php endif; ?>
                <div class="note-meta">
                    <?php foreach ($tagMap[$nid] ?? [] as $t): ?>
                        <a class="tag" href="<?= h(listUrl(['tagg' => $t])) ?>"><?= h($t) ?></a>
                    <?php endforeach; ?>
                    <span class="note-date" title="Skapad <?= h(fmtDate($n['Skapad'])) ?>">Redigerad <?= h(fmtDate($n['Redigerad'])) ?></span>
                </div>
                <?php if ($arkiv): ?>
                    <div class="note-card-actions">
                        <button type="button" class="secondary-btn unarchive-btn">↩ Återställ</button>
                        <button type="button" class="danger-btn delete-btn">Radera</button>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>

<script>
const CSRF = <?= json_encode(csrfToken()) ?>;

function post(fields) {
    const fd = new FormData();
    fd.append('csrf', CSRF);
    Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
    return fetch('anteckningar.php', { method: 'POST', body: fd }).then(r => r.json());
}

function handle(promise) {
    promise
        .then(data => { if (data.success) location.reload(); else alert(data.error || 'Något gick fel.'); })
        .catch(err => alert('Nätverksfel: ' + err.message));
}

document.querySelectorAll('.pin-btn').forEach(btn => btn.addEventListener('click', function () {
    const id = this.closest('.note-card').dataset.id;
    handle(post({ action: 'pin', id, fast: this.dataset.fast === '1' ? 0 : 1 }));
}));

document.querySelectorAll('.unarchive-btn').forEach(btn => btn.addEventListener('click', function () {
    handle(post({ action: 'unarchive', id: this.closest('.note-card').dataset.id }));
}));

document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', function () {
    const card = this.closest('.note-card');
    if (!confirm('Radera "' + card.dataset.namn + '" för alltid?\n\nDet går inte att ångra.')) return;
    handle(post({ action: 'delete', id: card.dataset.id }));
}));
</script>
</body>
</html>
