"""Run the actual identity-update SQL against representative team history."""
import re
import sqlite3
import unittest
from pathlib import Path
SOURCE = (Path(__file__).resolve().parents[1] / 'includi/sostituzione_giocatore.php').read_text()
UPDATES = re.findall(r"\$run\('(UPDATE [^']+)'", SOURCE)

class ReplacementTest(unittest.TestCase):
    def setUp(self):
        self.db = sqlite3.connect(':memory:')
        self.db.executescript('''
          CREATE TABLE partita_giocatore(id INTEGER PRIMARY KEY, partita_id, squadra_id, giocatore_id,
            goal, autogol, assist, presenza, voto, cartellino_giallo, cartellino_rosso,
            UNIQUE(partita_id, squadra_id, giocatore_id));
          CREATE TABLE giocatore_goal_extra(id INTEGER PRIMARY KEY, squadra_id, giocatore_id, goal);
          CREATE TABLE squadre_giocatori(squadra_id, giocatore_id, foto, reti, presenze, media_voti, is_captain,
            UNIQUE(squadra_id, giocatore_id));
          INSERT INTO partita_giocatore VALUES(1,100,10,7,3,1,2,1,8.5,1,0),(2,101,10,7,0,0,1,1,6,0,1),(3,102,20,7,5,0,0,1,9,0,0);
          INSERT INTO giocatore_goal_extra VALUES(1,10,7,2),(2,20,7,4),(3,NULL,7,1);
          INSERT INTO squadre_giocatori VALUES(10,7,'old.jpg',3,2,7.25,1),(20,7,'other.jpg',5,1,9,0);
        ''')

    def replace(self):
        with self.db:
            for sql in UPDATES:
                self.db.execute(sql, (8, 10, 7))

    def test_history_preserves_every_statistic_and_other_teams(self):
        before = self.db.execute('SELECT * FROM partita_giocatore ORDER BY id').fetchall()
        self.replace()
        after = self.db.execute('SELECT * FROM partita_giocatore ORDER BY id').fetchall()
        self.assertEqual(len(UPDATES), 3)
        for a,b in zip(before, after):
            self.assertEqual(a[:3], b[:3])
            self.assertEqual(a[4:], b[4:])
            self.assertEqual(b[3], 8 if a[2] == 10 else 7)
        self.assertEqual(self.db.execute('SELECT * FROM giocatore_goal_extra').fetchall(), [(1,10,8,2),(2,20,7,4),(3,None,7,1)])
        self.assertEqual(self.db.execute('SELECT * FROM squadre_giocatori WHERE squadra_id=10').fetchone(), (10,8,None,3,2,7.25,1))

    def test_conflict_rolls_back_all_updates(self):
        self.db.execute("INSERT INTO squadre_giocatori VALUES(10,8,NULL,1,1,6,0)")
        self.db.commit()
        before = self.db.execute('SELECT * FROM partita_giocatore').fetchall()
        with self.assertRaises(sqlite3.IntegrityError):
            self.replace()
        self.assertEqual(before, self.db.execute('SELECT * FROM partita_giocatore').fetchall())
        self.assertEqual(self.db.execute('SELECT giocatore_id FROM giocatore_goal_extra WHERE id=1').fetchone(), (7,))

if __name__ == '__main__': unittest.main()
