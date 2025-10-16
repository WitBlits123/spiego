"""
server.py

Flask server to receive activity logs from clients and store in SQLite database.
Provides web dashboard to view and analyze activity data.
"""
from flask import Flask, request, jsonify, render_template, send_from_directory
import sqlite3
import json
from datetime import datetime, timedelta
import os
from functools import wraps

app = Flask(__name__)
app.config['DATABASE'] = 'activity_logs.db'
app.config['AUTH_KEY'] = os.environ.get('AUTH_KEY', 'your-secret-auth-key-change-me')

def init_db():
    conn = sqlite3.connect(app.config['DATABASE'])
    cursor = conn.cursor()
    
    # Events table
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TEXT NOT NULL,
            event_type TEXT NOT NULL,
            hostname TEXT,
            data TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ''')
    
    # Devices table
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS devices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            hostname TEXT UNIQUE NOT NULL,
            platform TEXT,
            python_version TEXT,
            cpu_count INTEGER,
            memory_total INTEGER,
            last_seen DATETIME,
            mac_addresses TEXT
        )
    ''')
    
    # Create indexes
    cursor.execute('CREATE INDEX IF NOT EXISTS idx_events_timestamp ON events(timestamp)')
    cursor.execute('CREATE INDEX IF NOT EXISTS idx_events_type ON events(event_type)')
    cursor.execute('CREATE INDEX IF NOT EXISTS idx_events_hostname ON events(hostname)')
    
    conn.commit()
    conn.close()

def get_db():
    conn = sqlite3.connect(app.config['DATABASE'])
    conn.row_factory = sqlite3.Row
    return conn

def require_auth(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        auth_header = request.headers.get('Authorization', '')
        if not auth_header.startswith('Bearer '):
            return jsonify({'error': 'Missing authorization'}), 401
        token = auth_header.replace('Bearer ', '')
        if token != app.config['AUTH_KEY']:
            return jsonify({'error': 'Invalid authorization'}), 403
        return f(*args, **kwargs)
    return decorated

@app.route('/')
def index():
    return render_template('dashboard.html')

@app.route('/api/events', methods=['POST'])
@require_auth
def receive_events():
    try:
        data = request.get_json()
        events = data.get('events', [])
        
        conn = get_db()
        cursor = conn.cursor()
        
        for event in events:
            event_type = event.get('type', 'unknown')
            timestamp = event.get('timestamp', datetime.utcnow().isoformat())
            hostname = event.get('hostname', 'unknown')
            
            # Store event
            cursor.execute('''
                INSERT INTO events (timestamp, event_type, hostname, data)
                VALUES (?, ?, ?, ?)
            ''', (timestamp, event_type, hostname, json.dumps(event)))
            
            # Update device metadata if this is a metadata event
            if event_type == 'metadata':
                cursor.execute('''
                    INSERT OR REPLACE INTO devices 
                    (hostname, platform, python_version, cpu_count, memory_total, last_seen, mac_addresses)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ''', (
                    hostname,
                    event.get('platform'),
                    event.get('python_version'),
                    event.get('cpu_count'),
                    event.get('memory_total'),
                    datetime.utcnow(),
                    json.dumps(event.get('mac_addresses', []))
                ))
        
        conn.commit()
        conn.close()
        
        return jsonify({'status': 'success', 'received': len(events)}), 200
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/dashboard/stats')
def dashboard_stats():
    conn = get_db()
    cursor = conn.cursor()
    
    # Get device count
    cursor.execute('SELECT COUNT(*) as count FROM devices')
    device_count = cursor.fetchone()['count']
    
    # Get event count (last 24h)
    yesterday = (datetime.utcnow() - timedelta(days=1)).isoformat()
    cursor.execute('SELECT COUNT(*) as count FROM events WHERE timestamp > ?', (yesterday,))
    event_count_24h = cursor.fetchone()['count']
    
    # Get total events
    cursor.execute('SELECT COUNT(*) as count FROM events')
    total_events = cursor.fetchone()['count']
    
    # Get active devices (last 24h)
    cursor.execute('''
        SELECT COUNT(DISTINCT hostname) as count 
        FROM events 
        WHERE timestamp > ?
    ''', (yesterday,))
    active_devices = cursor.fetchone()['count']
    
    conn.close()
    
    return jsonify({
        'device_count': device_count,
        'event_count_24h': event_count_24h,
        'total_events': total_events,
        'active_devices': active_devices
    })

@app.route('/api/dashboard/devices')
def dashboard_devices():
    conn = get_db()
    cursor = conn.cursor()
    
    cursor.execute('''
        SELECT hostname, platform, python_version, cpu_count, 
               memory_total, last_seen, mac_addresses
        FROM devices
        ORDER BY last_seen DESC
    ''')
    
    devices = []
    for row in cursor.fetchall():
        devices.append({
            'hostname': row['hostname'],
            'platform': row['platform'],
            'python_version': row['python_version'],
            'cpu_count': row['cpu_count'],
            'memory_total': row['memory_total'],
            'last_seen': row['last_seen'],
            'mac_addresses': json.loads(row['mac_addresses']) if row['mac_addresses'] else []
        })
    
    conn.close()
    return jsonify({'devices': devices})

@app.route('/api/dashboard/recent_events')
def dashboard_recent_events():
    limit = request.args.get('limit', 100, type=int)
    event_type = request.args.get('type', None)
    hostname = request.args.get('hostname', None)
    
    conn = get_db()
    cursor = conn.cursor()
    
    query = 'SELECT * FROM events WHERE 1=1'
    params = []
    
    if event_type:
        query += ' AND event_type = ?'
        params.append(event_type)
    
    if hostname:
        query += ' AND hostname = ?'
        params.append(hostname)
    
    query += ' ORDER BY timestamp DESC LIMIT ?'
    params.append(limit)
    
    cursor.execute(query, params)
    
    events = []
    for row in cursor.fetchall():
        events.append({
            'id': row['id'],
            'timestamp': row['timestamp'],
            'event_type': row['event_type'],
            'hostname': row['hostname'],
            'data': json.loads(row['data'])
        })
    
    conn.close()
    return jsonify({'events': events})

@app.route('/api/dashboard/activity_timeline')
def dashboard_activity_timeline():
    hours = request.args.get('hours', 24, type=int)
    hostname = request.args.get('hostname', None)
    
    conn = get_db()
    cursor = conn.cursor()
    
    start_time = (datetime.utcnow() - timedelta(hours=hours)).isoformat()
    
    query = '''
        SELECT 
            strftime('%Y-%m-%d %H:00:00', timestamp) as hour,
            event_type,
            COUNT(*) as count
        FROM events
        WHERE timestamp > ?
    '''
    params = [start_time]
    
    if hostname:
        query += ' AND hostname = ?'
        params.append(hostname)
    
    query += ' GROUP BY hour, event_type ORDER BY hour'
    
    cursor.execute(query, params)
    
    timeline = {}
    for row in cursor.fetchall():
        hour = row['hour']
        if hour not in timeline:
            timeline[hour] = {}
        timeline[hour][row['event_type']] = row['count']
    
    conn.close()
    return jsonify({'timeline': timeline})

@app.route('/api/dashboard/top_domains')
def dashboard_top_domains():
    limit = request.args.get('limit', 20, type=int)
    hours = request.args.get('hours', 24, type=int)
    hostname = request.args.get('hostname', None)
    
    conn = get_db()
    cursor = conn.cursor()
    
    start_time = (datetime.utcnow() - timedelta(hours=hours)).isoformat()
    
    query = '''
        SELECT data
        FROM events
        WHERE event_type = 'foreground_change'
        AND timestamp > ?
    '''
    params = [start_time]
    
    if hostname:
        query += ' AND hostname = ?'
        params.append(hostname)
    
    cursor.execute(query, params)
    
    domain_counts = {}
    for row in cursor.fetchall():
        try:
            event_data = json.loads(row['data'])
            title = event_data.get('title', '')
            url = event_data.get('url')
            
            # Extract domain from URL or title
            domain = None
            if url:
                # Simple domain extraction
                try:
                    from urllib.parse import urlparse
                    parsed = urlparse(url if url.startswith('http') else f'http://{url}')
                    domain = parsed.netloc or parsed.path.split('/')[0]
                except:
                    domain = url[:50]
            elif title:
                # Use title if no URL
                domain = title[:80]
            
            if domain:
                domain_counts[domain] = domain_counts.get(domain, 0) + 1
        except:
            pass
    
    # Sort by count
    top_domains = sorted(domain_counts.items(), key=lambda x: x[1], reverse=True)[:limit]
    
    conn.close()
    return jsonify({'domains': [{'domain': d, 'count': c} for d, c in top_domains]})

if __name__ == '__main__':
    init_db()
    print("Server starting...")
    print("Auth key:", app.config['AUTH_KEY'])
    print("Dashboard: http://127.0.0.1:5000")
    app.run(host='0.0.0.0', port=5000, debug=True)
