/* ============================================
   KeySynx — Camelot Wheel & Harmonic Engine
   Beyond-MVP feature:
   1. Interactive Camelot Wheel Visualization
   2. Harmonic Transition Recommendation Engine
   ============================================ */

// Standard Camelot Wheel mapping (Mixed In Key convention).
// Modal keys (Mixolydian, Dorian, etc.) are intentionally NOT mapped —
// the engine only scores tracks where a clean Camelot code exists.
const CAMELOT_MAP = {
  "B Major": "1B",  "G# Minor": "1A", "Ab Minor": "1A",
  "F# Major": "2B", "Gb Major": "2B", "Eb Minor": "2A", "D# Minor": "2A",
  "Db Major": "3B", "C# Major": "3B", "Bb Minor": "3A", "A# Minor": "3A",
  "Ab Major": "4B", "G# Major": "4B", "F Minor": "4A",
  "Eb Major": "5B", "D# Major": "5B", "C Minor": "5A",
  "Bb Major": "6B", "A# Major": "6B", "G Minor": "6A",
  "F Major": "7B",  "D Minor": "7A",
  "C Major": "8B",  "A Minor": "8A",
  "G Major": "9B",  "E Minor": "9A",
  "D Major": "10B", "B Minor": "10A",
  "A Major": "11B", "F# Minor": "11A", "Gb Minor": "11A",
  "E Major": "12B", "C# Minor": "12A", "Db Minor": "12A"
};

function getCamelotCode(musicalKey){
  return CAMELOT_MAP[musicalKey] || null;
}

function parseCamelot(code){
  const num = parseInt(code, 10);
  const letter = code.replace(/[0-9]/g, '');
  return { num, letter };
}

function wrap12(n){
  return ((n - 1 + 12) % 12) + 1;
}

// Returns the set of codes considered harmonically compatible with `code`,
// tagged with a human-readable relation label.
function getCompatibleCodes(code){
  const { num, letter } = parseCamelot(code);
  const otherLetter = letter === 'A' ? 'B' : 'A';
  return {
    same: code,
    relative: `${num}${otherLetter}`,
    energyUp: `${wrap12(num + 1)}${letter}`,
    energyDown: `${wrap12(num - 1)}${letter}`
  };
}

// Core of the Harmonic Transition Recommendation Engine.
// Scores how smooth a transition from `fromSong` to `toSong` would be,
// combining Camelot key compatibility with BPM proximity.
function computeTransitionScore(fromSong, toSong){
  const codeA = getCamelotCode(fromSong.musicalKey);
  const codeB = getCamelotCode(toSong.musicalKey);

  if(!codeA || !codeB){
    return { score: 0, relation: 'Key not mappable to wheel', keyScore: 0, bpmScore: 0, codeA, codeB };
  }

  const { num: numA, letter: letA } = parseCamelot(codeA);
  const { num: numB, letter: letB } = parseCamelot(codeB);

  let relation, keyScore;
  if(codeA === codeB){
    relation = 'Perfect match — same key';
    keyScore = 100;
  } else if(numA === numB && letA !== letB){
    relation = 'Relative major/minor';
    keyScore = 90;
  } else if(letA === letB && wrap12(numA + 1) === numB){
    relation = 'Energy boost (+1 step)';
    keyScore = 80;
  } else if(letA === letB && wrap12(numA - 1) === numB){
    relation = 'Energy drop (-1 step)';
    keyScore = 80;
  } else {
    relation = 'Less compatible';
    keyScore = 30;
  }

  const bpmDiffPct = (fromSong.bpm && toSong.bpm) ? Math.abs(fromSong.bpm - toSong.bpm) / fromSong.bpm * 100 : null;
  let bpmScore;
  if(bpmDiffPct === null) bpmScore = 0;
  else if(bpmDiffPct <= 2) bpmScore = 100;
  else if(bpmDiffPct <= 6) bpmScore = 75;
  else if(bpmDiffPct <= 12) bpmScore = 45;
  else bpmScore = 15;

  const score = Math.round(keyScore * 0.6 + bpmScore * 0.4);
  const bpmDiff = (fromSong.bpm && toSong.bpm) ? Math.round(Math.abs(fromSong.bpm - toSong.bpm) * 100) / 100 : null;

  return { score, relation, keyScore, bpmScore, bpmDiff, codeA, codeB };
}

// Returns all other songs ranked by transition score, best first.
function getRecommendations(currentSong, allSongs, limit = 5){
  return allSongs
    .filter(s => s.id !== currentSong.id)
    .map(s => ({ song: s, ...computeTransitionScore(currentSong, s) }))
    .filter(r => r.score > 0)
    .sort((a, b) => b.score - a.score)
    .slice(0, limit);
}
