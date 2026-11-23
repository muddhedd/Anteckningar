<?php
/**
 * demo.php – sandlåda för Anteckningar (används bara när AUTH_MODE = 'demo')
 * Exempeldatan ligger under UserID 0 (se sql/demo.sql) och kopieras till
 * varje ny besökare. Datumen flyttas så att anteckningarna ser färska ut.
 */
if (!defined('AUTH_MODE')) {
    http_response_code(403);
    exit;
}

function demoClone(int $uid): void {
    $db = getDb();
    mysqli_begin_transaction($db);

    $max = dbOne('SELECT MAX(Redigerad) AS M FROM Anteckningar WHERE UserID = 0');
    $off = !empty($max['M']) ? (time() - 3600) - strtotime($max['M']) : 0;   // senaste ändringen = för en timme sedan
    $shift = function ($d) use ($off) { return date('Y-m-d H:i:s', strtotime($d) + $off); };

    $tagMap = [];
    foreach (dbAll('SELECT ID, Namn FROM AnteckningTaggar WHERE UserID = 0 ORDER BY ID') as $t) {
        dbRun('INSERT INTO AnteckningTaggar (UserID, Namn) VALUES (?, ?)', 'is', [$uid, $t['Namn']]);
        $tagMap[(int)$t['ID']] = dbInsertId();
    }

    $noteMap = [];
    foreach (dbAll('SELECT * FROM Anteckningar WHERE UserID = 0 ORDER BY ID') as $n) {
        dbRun(
            'INSERT INTO Anteckningar (UserID, Namn, Text, Fast, Arkiverad, Version, Skapad, Redigerad)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
            'issiiss', [$uid, $n['Namn'], $n['Text'], (int)$n['Fast'], (int)$n['Arkiverad'], $shift($n['Skapad']), $shift($n['Redigerad'])]
        );
        $noteMap[(int)$n['ID']] = dbInsertId();
    }

    $links = dbAll(
        'SELECT k.AnteckningID, k.TaggID FROM AnteckningTaggKoppling k
         JOIN Anteckningar a ON a.ID = k.AnteckningID WHERE a.UserID = 0'
    );
    foreach ($links as $l) {
        $n = $noteMap[(int)$l['AnteckningID']] ?? null;
        $t = $tagMap[(int)$l['TaggID']] ?? null;
        if ($n && $t) {
            dbRun('INSERT IGNORE INTO AnteckningTaggKoppling (AnteckningID, TaggID) VALUES (?, ?)', 'ii', [$n, $t]);
        }
    }

    mysqli_commit($db);
}

function demoPurge(array $uids): void {
    $in = demoIdList($uids);
    dbRun("DELETE FROM Anteckningar WHERE UserID IN ($in)");        // kopplingar försvinner automatiskt
    dbRun("DELETE FROM AnteckningTaggar WHERE UserID IN ($in)");
}
