"""Regression checks for team-scoped match reports (stdlib SQLite SQL harness)."""
import re
import sqlite3
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
API = (ROOT / 'api/partita_giocatore.php').read_text()


class PlayerTeamStatsTest(unittest.TestCase):
    def setUp(self):
        self.db = sqlite3.connect(':memory:')
        self.db.executescript('''
            CREATE TABLE partite(id INTEGER PRIMARY KEY, torneo TEXT, squadra_casa TEXT, squadra_ospite TEXT);
            CREATE TABLE squadre(id INTEGER PRIMARY KEY, torneo TEXT, nome TEXT);
            CREATE TABLE giocatori(id INTEGER PRIMARY KEY, nome TEXT, cognome TEXT);
            CREATE TABLE squadre_giocatori(squadra_id INTEGER, giocatore_id INTEGER, ruolo TEXT, is_captain INTEGER);
            CREATE TABLE partita_giocatore(id INTEGER PRIMARY KEY, partita_id INTEGER, giocatore_id INTEGER,
                squadra_id INTEGER, presenza INTEGER, goal INTEGER, autogol INTEGER, assist INTEGER,
                cartellino_giallo INTEGER, cartellino_rosso INTEGER, voto REAL,
                UNIQUE(partita_id, giocatore_id, squadra_id));
            INSERT INTO partite VALUES(1, 'coppa', 'A', 'B');
            INSERT INTO squadre VALUES(10, 'coppa', 'A'), (20, 'coppa', 'B');
            INSERT INTO giocatori VALUES(7, 'Mario', 'Rossi');
            INSERT INTO squadre_giocatori VALUES(10, 7, 'Attaccante', 0), (20, 7, 'Attaccante', 0);
        ''')
        # Execute the application's upsert, translating only MySQL's conflict syntax.
        self.upsert = re.search(r'\$upsert = \$conn->prepare\("(.*?)"\);', API, re.S)[1]
        self.upsert = self.upsert.replace('ON DUPLICATE KEY UPDATE', 'ON CONFLICT(partita_id, giocatore_id, squadra_id) DO UPDATE SET')
        self.upsert = re.sub(r'VALUES\((\w+)\)', r'excluded.\1', self.upsert)
        self.db.execute(self.upsert, (1, 7, 10, 2, 0, 1, 0, 0, 8))
        self.db.execute(self.upsert, (1, 7, 20, 0, 1, 0, 1, 0, 5))

    def test_reload_keeps_different_votes_and_stats(self):
        lineup = API.split("if ($azione === 'lineup')")[1].split('$stmt = $conn->prepare("', 1)[1].split('");', 1)[0]
        self.db.row_factory = sqlite3.Row
        rows = [dict(r) for r in self.db.execute(lineup, (1,))]
        self.assertEqual(len(rows), 2)
        self.assertEqual({r['squadra_id']: (r['goal'], r['voto']) for r in rows}, {10: (2, 8), 20: (0, 5)})

    def test_update_and_remove_one_side_leave_opponent_unchanged(self):
        self.db.execute(self.upsert, (1, 7, 10, 3, 0, 2, 0, 0, 9))
        delete = re.search(r"\$delete = \$conn->prepare\('(.*?)'\);", API)[1]
        self.db.execute(delete, (1, 7, 10))
        self.assertEqual(self.db.execute('SELECT squadra_id, goal, autogol, voto FROM partita_giocatore').fetchall(), [(20, 0, 1, 5)])

    def test_legacy_picker_offers_only_unsaved_team(self):
        self.db.execute('DELETE FROM partita_giocatore WHERE squadra_id = 20')
        section = API.split("if ($azione === 'list_giocatori')")[1]
        query = section.split('$sql = "', 1)[1].split('";', 1)[0]
        rows = self.db.execute(query, ('coppa', 'A', 'B', 1)).fetchall()
        self.assertEqual([r[3] for r in rows], [20])


if __name__ == '__main__':
    unittest.main()
