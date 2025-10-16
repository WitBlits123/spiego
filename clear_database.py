"""
clear_database.py

Deletes the SQLite database file so you can start fresh.
The database will be recreated automatically when the server starts.
"""
import os

db_file = 'activity_logs.db'

if os.path.exists(db_file):
    os.remove(db_file)
    print(f"✅ Database '{db_file}' deleted successfully.")
    print("The server will create a new empty database on next startup.")
else:
    print(f"Database '{db_file}' not found. Nothing to delete.")
