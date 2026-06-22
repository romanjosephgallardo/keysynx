/* ============================================
   KeySynx — Sample Data Layer
   Source: community key/BPM analysis of
   Ariana Grande — Eternal Sunshine (2024)
   This stands in for the PHP/MySQL backend.
   Swap fetchSongs()/fetchSongById() for real
   fetch() calls to api/songs.php once wired up.
   ============================================ */

const ALBUM = {
  title: "Eternal Sunshine",
  artist: "Ariana Grande",
  year: 2024
};

const ETERNAL_SUNSHINE_TRACKS = [
  {
    id: 1,
    title: "intro (end of the world)",
    artist: "Ariana Grande",
    bpm: 85,
    musicalKey: "Bb Major",
    hasVariation: false,
    sectionKeys: [],
    footnote: "Borrows Gmaj & Dmaj throughout",
    timeSignature: null,
    submittedBy: "community",
    status: "verified",
    upvotes: 12,
    downvotes: 0
  },
  {
    id: 2,
    title: "bye",
    artist: "Ariana Grande",
    bpm: 110,
    musicalKey: "D Minor",
    hasVariation: true,
    sectionKeys: [
      { section: "Bridge", key: "F Minor" },
      { section: "Rest", key: "D Minor" }
    ],
    footnote: null,
    timeSignature: null,
    submittedBy: "community",
    status: "verified",
    upvotes: 10,
    downvotes: 0
  },
  {
    id: 3,
    title: "don't wanna break up again",
    artist: "Ariana Grande",
    bpm: 97,
    musicalKey: "F Major",
    hasVariation: false,
    sectionKeys: [],
    footnote: "Borrows B7 throughout; bridge borrows Abmaj & F#maj",
    timeSignature: null,
    submittedBy: "community",
    status: "verified",
    upvotes: 8,
    downvotes: 0
  },
  {
    id: 4,
    title: "eternal sunshine",
    artist: "Ariana Grande",
    bpm: 80,
    musicalKey: "A Major",
    hasVariation: false,
    sectionKeys: [],
    footnote: "Instrumental subtly borrows Dbmaj throughout",
    timeSignature: null,
    submittedBy: "community",
    status: "verified",
    upvotes: 15,
    downvotes: 0
  },
  {
    id: 5,
    title: "supernatural",
    artist: "Ariana Grande",
    bpm: 153,
    musicalKey: "Ab Major",
    hasVariation: false,
    sectionKeys: [],
    footnote: null,
    timeSignature: null,
    submittedBy: "community",
    status: "verified",
    upvotes: 11,
    downvotes: 0
  },
  {
    id: 6,
    title: "true story",
    artist: "Ariana Grande",
    bpm: 138,
    musicalKey: "A Minor",
    hasVariation: false,
    sectionKeys: [],
    footnote: "Borrows Emaj throughout; would work well as harmonic minor",
    timeSignature: null,
    submittedBy: "community",
    status: "pending",
    upvotes: 4,
    downvotes: 1
  },
  {
    id: 7,
    title: "the boy is mine",
    artist: "Ariana Grande",
    bpm: 98,
    musicalKey: "G Minor",
    hasVariation: false,
    sectionKeys: [],
    footnote: "Quick ritard before the chorus; borrows Dmaj at end of bridge",
    timeSignature: null,
    submittedBy: "community",
    status: "verified",
    upvotes: 9,
    downvotes: 0
  },
  {
    id: 8,
    title: "yes, and?",
    artist: "Ariana Grande",
    bpm: 119,
    musicalKey: "Bb Minor",
    hasVariation: true,
    sectionKeys: [
      { section: "Verse & Bridge", key: "Bb Mixolydian" },
      { section: "Rest", key: "Bb Minor" }
    ],
    footnote: null,
    timeSignature: null,
    submittedBy: "community",
    status: "verified",
    upvotes: 18,
    downvotes: 0
  },
  {
    id: 9,
    title: "we can't be friends (wait for your love)",
    artist: "Ariana Grande",
    bpm: 116,
    musicalKey: "F Major",
    hasVariation: false,
    sectionKeys: [],
    footnote: null,
    timeSignature: null,
    submittedBy: "community",
    status: "verified",
    upvotes: 21,
    downvotes: 0
  },
  {
    id: 10,
    title: "i wish i hated you",
    artist: "Ariana Grande",
    bpm: 98.25,
    musicalKey: "G Major",
    hasVariation: false,
    sectionKeys: [],
    footnote: null,
    timeSignature: "6/8",
    submittedBy: "community",
    status: "pending",
    upvotes: 5,
    downvotes: 0
  },
  {
    id: 11,
    title: "imperfect for you",
    artist: "Ariana Grande",
    bpm: 75,
    musicalKey: "E Major",
    hasVariation: false,
    sectionKeys: [],
    footnote: "Borrows Fmaj, Bbm, F#maj",
    timeSignature: "6/8",
    submittedBy: "community",
    status: "pending",
    upvotes: 3,
    downvotes: 0
  },
  {
    id: 12,
    title: "ordinary things",
    artist: "Ariana Grande",
    bpm: 115,
    musicalKey: "Db Major",
    hasVariation: false,
    sectionKeys: [],
    footnote: "Borrows G#m throughout",
    timeSignature: null,
    submittedBy: "community",
    status: "verified",
    upvotes: 13,
    downvotes: 0
  }
];

// ---- Stand-in "API" functions (swap with real fetch() later) ----
function fetchSongs(){
  return Promise.resolve(ETERNAL_SUNSHINE_TRACKS);
}
function fetchSongById(id){
  return Promise.resolve(ETERNAL_SUNSHINE_TRACKS.find(s => s.id === Number(id)));
}
function fetchAlbum(){
  return Promise.resolve(ALBUM);
}
