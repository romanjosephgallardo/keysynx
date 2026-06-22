<?php
/**
 * KeySynx — Camelot Wheel & Harmonic Engine (PHP port)
 * Mirrors js/camelot.js so the same scoring logic is available
 * server-side for search filtering and the recommendation endpoint.
 */

const CAMELOT_MAP = [
    "B Major" => "1B",  "G# Minor" => "1A", "Ab Minor" => "1A",
    "F# Major" => "2B", "Gb Major" => "2B", "Eb Minor" => "2A", "D# Minor" => "2A",
    "Db Major" => "3B", "C# Major" => "3B", "Bb Minor" => "3A", "A# Minor" => "3A",
    "Ab Major" => "4B", "G# Major" => "4B", "F Minor" => "4A",
    "Eb Major" => "5B", "D# Major" => "5B", "C Minor" => "5A",
    "Bb Major" => "6B", "A# Major" => "6B", "G Minor" => "6A",
    "F Major" => "7B",  "D Minor" => "7A",
    "C Major" => "8B",  "A Minor" => "8A",
    "G Major" => "9B",  "E Minor" => "9A",
    "D Major" => "10B", "B Minor" => "10A",
    "A Major" => "11B", "F# Minor" => "11A", "Gb Minor" => "11A",
    "E Major" => "12B", "C# Minor" => "12A", "Db Minor" => "12A",
];

function getCamelotCode(string $musicalKey): ?string {
    return CAMELOT_MAP[$musicalKey] ?? null;
}

function parseCamelot(string $code): array {
    preg_match('/(\d+)([AB])/', $code, $m);
    return ['num' => (int)$m[1], 'letter' => $m[2]];
}

function wrap12(int $n): int {
    return (($n - 1 + 12) % 12) + 1;
}

function getCompatibleCodes(string $code): array {
    $p = parseCamelot($code);
    $other = $p['letter'] === 'A' ? 'B' : 'A';
    return [
        'same'       => $code,
        'relative'   => $p['num'] . $other,
        'energyUp'   => wrap12($p['num'] + 1) . $p['letter'],
        'energyDown' => wrap12($p['num'] - 1) . $p['letter'],
    ];
}

/**
 * Scores a transition from $fromSong to $toSong.
 * Both are associative arrays with at least 'musical_key' and 'bpm'.
 */
function computeTransitionScore(array $fromSong, array $toSong): array {
    $codeA = getCamelotCode($fromSong['musical_key']);
    $codeB = getCamelotCode($toSong['musical_key']);

    if (!$codeA || !$codeB) {
        return ['score' => 0, 'relation' => 'Key not mappable to wheel', 'codeA' => $codeA, 'codeB' => $codeB];
    }

    $a = parseCamelot($codeA);
    $b = parseCamelot($codeB);

    if ($codeA === $codeB) {
        $relation = 'Perfect match — same key';
        $keyScore = 100;
    } elseif ($a['num'] === $b['num'] && $a['letter'] !== $b['letter']) {
        $relation = 'Relative major/minor';
        $keyScore = 90;
    } elseif ($a['letter'] === $b['letter'] && wrap12($a['num'] + 1) === $b['num']) {
        $relation = 'Energy boost (+1 step)';
        $keyScore = 80;
    } elseif ($a['letter'] === $b['letter'] && wrap12($a['num'] - 1) === $b['num']) {
        $relation = 'Energy drop (-1 step)';
        $keyScore = 80;
    } else {
        $relation = 'Less compatible';
        $keyScore = 30;
    }

    $bpmDiffPct = ($fromSong['bpm'] && $toSong['bpm'])
        ? abs($fromSong['bpm'] - $toSong['bpm']) / $fromSong['bpm'] * 100
        : null;

    if ($bpmDiffPct === null) $bpmScore = 0;
    elseif ($bpmDiffPct <= 2) $bpmScore = 100;
    elseif ($bpmDiffPct <= 6) $bpmScore = 75;
    elseif ($bpmDiffPct <= 12) $bpmScore = 45;
    else $bpmScore = 15;

    $score = round($keyScore * 0.6 + $bpmScore * 0.4);

    return [
        'score' => $score,
        'relation' => $relation,
        'keyScore' => $keyScore,
        'bpmScore' => $bpmScore,
        'bpmDiff' => ($fromSong['bpm'] && $toSong['bpm']) ? round(abs($fromSong['bpm'] - $toSong['bpm']), 2) : null,
        'codeA' => $codeA,
        'codeB' => $codeB,
    ];
}
