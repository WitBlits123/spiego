import sqlite3
import os

db = os.path.join(r"C:\WebSites\HugoApp\Spiego", 'database', 'database.sqlite')
if not os.path.exists(db):
    print('Database not found:', db)
    raise SystemExit(1)

conn = sqlite3.connect(db)
cur = conn.cursor()

cur.execute('SELECT id, hostname, domain, created_at FROM blocked_sites ORDER BY hostname, domain')
rows = cur.fetchall()
if not rows:
    print('No blocked_sites rows found.')
else:
    print('id | hostname | domain | created_at')
    for r in rows:
        print(' | '.join(str(x) for x in r))

conn.close()
