"""
server_tray.py

Flask server that runs in the Windows system tray with no console window.
Receives activity logs from clients and stores in SQLite database.
Provides web dashboard to view and analyze activity data.

Right-click the tray icon to view server status or open dashboard.
"""
import os
import sys
import json
import threading
import webbrowser
import socket
from datetime import datetime, timedelta

# Flask imports
from flask import Flask, request, jsonify, render_template, send_from_directory
import sqlite3
from functools import wraps

# System tray imports
try:
    import pystray
    from PIL import Image, ImageDraw
except ImportError:
    pystray = None

try:
    import win32api
    import win32con
except ImportError:
    win32api = None

# Flask app setup
app = Flask(__name__)
app.config['DATABASE'] = 'activity_logs.db'
app.config['AUTH_KEY'] = os.environ.get('AUTH_KEY', 'your-secret-auth-key-change-me')
# License handling
# Hardcoded valid license for now
VALID_LICENSE = 'spiegoishugo'
app.config['LICENSE_KEY'] = os.environ.get('LICENSE_KEY', '')
app.config['SESSION_START'] = datetime.now()

# Global stats
stats = {
    'total_events': 0,
    'active_devices': 0,
    'start_time': datetime.now(),
    'last_event': None
}

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


@app.before_request
def enforce_license():
    # Allow static/template requests without enforcement
    path = request.path
    if path.startswith('/static') or path.startswith('/favicon.ico') or path.startswith('/templates'):
        return None

    # Check license
    provided = app.config.get('LICENSE_KEY') or request.args.get('license') or request.headers.get('X-License-Key')
    if provided == VALID_LICENSE:
        return None

    # If license invalid, allow only within 10 minutes of session start
    start = app.config.get('SESSION_START') or datetime.now()
    if datetime.now() - start <= timedelta(minutes=10):
        return None

    # Otherwise, reject with trial expired page or JSON error
    if request.path.startswith('/api'):
        return jsonify({'error': 'Trial expired - invalid license'}), 403
    else:
        return render_template('trial_expired.html'), 403

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
            
            # Update stats
            stats['total_events'] += 1
            stats['last_event'] = datetime.now()
            
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
                    event.get('platform', 'unknown'),
                    event.get('python_version', 'unknown'),
                    event.get('cpu_count', 0),
                    event.get('memory_total', 0),
                    timestamp,
                    json.dumps(event.get('mac_addresses', []))
                ))
        
        conn.commit()
        conn.close()
        
        # Update active devices count
        update_device_count()
        
        return jsonify({'status': 'success', 'received': len(events)}), 200
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/api/stats', methods=['GET'])
def get_stats():
    conn = get_db()
    cursor = conn.cursor()
    
    # Total events
    cursor.execute('SELECT COUNT(*) as count FROM events')
    total_events = cursor.fetchone()['count']
    
    # Active devices (seen in last 5 minutes)
    cursor.execute('''
        SELECT COUNT(DISTINCT hostname) as count 
        FROM events 
        WHERE datetime(timestamp) > datetime('now', '-5 minutes')
    ''')
    active_devices = cursor.fetchone()['count']
    
    # Events by type
    cursor.execute('''
        SELECT event_type, COUNT(*) as count 
        FROM events 
        GROUP BY event_type
    ''')
    events_by_type = {row['event_type']: row['count'] for row in cursor.fetchall()}
    
    conn.close()
    
    return jsonify({
        'total_events': total_events,
        'active_devices': active_devices,
        'events_by_type': events_by_type
    })

@app.route('/api/devices', methods=['GET'])
def get_devices():
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
    return jsonify(devices)

@app.route('/api/recent-events', methods=['GET'])
def get_recent_events():
    hostname = request.args.get('hostname')
    event_type = request.args.get('type')
    hours = int(request.args.get('hours', 24))
    
    conn = get_db()
    cursor = conn.cursor()
    
    query = '''
        SELECT timestamp, event_type, hostname, data 
        FROM events 
        WHERE datetime(timestamp) > datetime('now', '-{} hours')
    '''.format(hours)
    
    params = []
    if hostname:
        query += ' AND hostname = ?'
        params.append(hostname)
    if event_type:
        query += ' AND event_type = ?'
        params.append(event_type)
    
    query += ' ORDER BY timestamp DESC LIMIT 100'
    
    cursor.execute(query, params)
    events = []
    for row in cursor.fetchall():
        event_data = json.loads(row['data'])
        events.append({
            'timestamp': row['timestamp'],
            'event_type': row['event_type'],
            'hostname': row['hostname'],
            'data': event_data
        })
    conn.close()
    return jsonify(events)

@app.route('/api/activity-timeline', methods=['GET'])
def get_activity_timeline():
    hostname = request.args.get('hostname')
    hours = int(request.args.get('hours', 24))
    
    conn = get_db()
    cursor = conn.cursor()
    
    query = '''
        SELECT strftime('%Y-%m-%d %H:00:00', timestamp) as hour,
               event_type,
               COUNT(*) as count
        FROM events
        WHERE datetime(timestamp) > datetime('now', '-{} hours')
    '''.format(hours)
    
    params = []
    if hostname:
        query += ' AND hostname = ?'
        params.append(hostname)
    
    query += ' GROUP BY hour, event_type ORDER BY hour'
    
    cursor.execute(query, params)
    timeline = []
    for row in cursor.fetchall():
        timeline.append({
            'hour': row['hour'],
            'event_type': row['event_type'],
            'count': row['count']
        })
    conn.close()
    return jsonify(timeline)

@app.route('/api/top-domains', methods=['GET'])
def get_top_domains():
    hostname = request.args.get('hostname')
    hours = int(request.args.get('hours', 24))
    
    conn = get_db()
    cursor = conn.cursor()
    
    query = '''
        SELECT data
        FROM events
        WHERE event_type = 'foreground_change'
        AND datetime(timestamp) > datetime('now', '-{} hours')
    '''.format(hours)
    
    params = []
    if hostname:
        query += ' AND hostname = ?'
        params.append(hostname)
    
    cursor.execute(query, params)
    
    domain_counts = {}
    for row in cursor.fetchall():
        try:
            event_data = json.loads(row['data'])
            url = event_data.get('url', '')
            if url and url.startswith('http'):
                # Extract domain
                domain = url.split('/')[2] if len(url.split('/')) > 2 else url
                domain_counts[domain] = domain_counts.get(domain, 0) + 1
        except:
            pass
    
    conn.close()
    
    # Sort by count and return top 10
    top_domains = sorted(domain_counts.items(), key=lambda x: x[1], reverse=True)[:10]
    return jsonify([{'domain': d, 'count': c} for d, c in top_domains])

def update_device_count():
    conn = get_db()
    cursor = conn.cursor()
    cursor.execute('''
        SELECT COUNT(DISTINCT hostname) as count 
        FROM events 
        WHERE datetime(timestamp) > datetime('now', '-5 minutes')
    ''')
    stats['active_devices'] = cursor.fetchone()['count']
    conn.close()

# Dashboard API routes (expected by dashboard.html)
@app.route('/api/dashboard/stats', methods=['GET'])
def get_dashboard_stats():
    conn = get_db()
    cursor = conn.cursor()
    
    # Total devices
    cursor.execute('SELECT COUNT(DISTINCT hostname) as count FROM devices')
    device_count = cursor.fetchone()['count']
    
    # Active devices (seen in last 5 minutes)
    cursor.execute('''
        SELECT COUNT(DISTINCT hostname) as count 
        FROM events 
        WHERE datetime(timestamp) > datetime('now', '-5 minutes')
    ''')
    active_devices = cursor.fetchone()['count']
    
    # Events in last 24 hours
    cursor.execute('''
        SELECT COUNT(*) as count 
        FROM events 
        WHERE datetime(timestamp) > datetime('now', '-24 hours')
    ''')
    event_count_24h = cursor.fetchone()['count']
    
    # Total events
    cursor.execute('SELECT COUNT(*) as count FROM events')
    total_events = cursor.fetchone()['count']
    
    conn.close()
    
    return jsonify({
        'device_count': device_count,
        'active_devices': active_devices,
        'event_count_24h': event_count_24h,
        'total_events': total_events
    })

@app.route('/api/dashboard/devices', methods=['GET'])
def get_dashboard_devices():
    conn = get_db()
    cursor = conn.cursor()
    
    cursor.execute('''
        SELECT d.hostname, d.platform, d.python_version, d.cpu_count, 
               d.memory_total, d.last_seen, d.mac_addresses,
               COUNT(e.id) as event_count
        FROM devices d
        LEFT JOIN events e ON d.hostname = e.hostname 
            AND datetime(e.timestamp) > datetime('now', '-24 hours')
        GROUP BY d.hostname
        ORDER BY d.last_seen DESC
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
            'event_count': row['event_count']
        })
    
    conn.close()
    return jsonify({'devices': devices})

@app.route('/api/dashboard/activity_timeline', methods=['GET'])
def get_dashboard_timeline():
    hours = int(request.args.get('hours', 24))
    hostname = request.args.get('hostname')
    
    conn = get_db()
    cursor = conn.cursor()
    
    query = '''
        SELECT strftime('%Y-%m-%d %H:00:00', timestamp) as hour,
               event_type,
               COUNT(*) as count
        FROM events
        WHERE datetime(timestamp) > datetime('now', '-{} hours')
    '''.format(hours)
    
    params = []
    if hostname:
        query += ' AND hostname = ?'
        params.append(hostname)
    
    query += ' GROUP BY hour, event_type ORDER BY hour'
    
    cursor.execute(query, params)
    
    # Organize by hour and event type
    timeline = {}
    for row in cursor.fetchall():
        hour = row['hour']
        event_type = row['event_type']
        count = row['count']
        
        if hour not in timeline:
            timeline[hour] = {}
        timeline[hour][event_type] = count
    
    conn.close()
    return jsonify({'timeline': timeline})

@app.route('/api/dashboard/top_domains', methods=['GET'])
def get_dashboard_top_domains():
    hours = int(request.args.get('hours', 24))
    hostname = request.args.get('hostname')
    limit = int(request.args.get('limit', 20))
    
    conn = get_db()
    cursor = conn.cursor()
    
    query = '''
        SELECT data
        FROM events
        WHERE event_type = 'foreground_change'
        AND datetime(timestamp) > datetime('now', '-{} hours')
    '''.format(hours)
    
    params = []
    if hostname:
        query += ' AND hostname = ?'
        params.append(hostname)
    
    cursor.execute(query, params)
    
    app_counts = {}
    domain_counts = {}
    
    for row in cursor.fetchall():
        try:
            event_data = json.loads(row['data'])
            
            # Count applications by process name
            process_name = event_data.get('process_name', 'Unknown')
            if process_name and process_name != 'Unknown':
                app_counts[process_name] = app_counts.get(process_name, 0) + 1
            
            # Also count domains from URLs
            url = event_data.get('url', '')
            if url and url.startswith('http'):
                # Extract domain
                domain = url.split('/')[2] if len(url.split('/')) > 2 else url
                domain_counts[domain] = domain_counts.get(domain, 0) + 1
        except:
            pass
    
    conn.close()
    
    # Combine apps and domains, prioritize apps
    combined = []
    
    # Add top applications
    for app, count in sorted(app_counts.items(), key=lambda x: x[1], reverse=True)[:limit]:
        combined.append({'domain': app, 'count': count, 'type': 'app'})
    
    # Add top domains if we have space
    remaining = limit - len(combined)
    if remaining > 0:
        for domain, count in sorted(domain_counts.items(), key=lambda x: x[1], reverse=True)[:remaining]:
            combined.append({'domain': domain, 'count': count, 'type': 'url'})
    
    return jsonify({'domains': combined})

@app.route('/api/dashboard/recent_events', methods=['GET'])
def get_dashboard_recent_events():
    limit = int(request.args.get('limit', 50))
    page = int(request.args.get('page', 1))
    hostname = request.args.get('hostname')
    event_type = request.args.get('type')
    hours = int(request.args.get('hours', 24))
    app_filter = request.args.get('app')  # Filter for app/domain
    
    conn = get_db()
    cursor = conn.cursor()
    
    # Build base query
    query = '''
        SELECT timestamp, event_type, hostname, data 
        FROM events 
        WHERE datetime(timestamp) > datetime('now', '-{} hours')
    '''.format(hours)
    
    params = []
    if hostname:
        query += ' AND hostname = ?'
        params.append(hostname)
    if event_type:
        query += ' AND event_type = ?'
        params.append(event_type)
    
    # If filtering by app, add JSON filter
    if app_filter:
        query += ''' AND (
            json_extract(data, '$.process_name') = ? 
            OR json_extract(data, '$.url') LIKE ?
        )'''
        params.append(app_filter)
        params.append(f'%{app_filter}%')
    
    # Get total count
    count_query = f"SELECT COUNT(*) as total FROM ({query})"
    cursor.execute(count_query, params)
    total_count = cursor.fetchone()['total']
    
    # Add pagination
    offset = (page - 1) * limit
    query += ' ORDER BY timestamp DESC LIMIT ? OFFSET ?'
    params.extend([limit, offset])
    
    cursor.execute(query, params)
    
    events = []
    for row in cursor.fetchall():
        event_data = json.loads(row['data'])
        events.append({
            'timestamp': row['timestamp'],
            'event_type': row['event_type'],
            'hostname': row['hostname'],
            'data': event_data
        })
    
    conn.close()
    
    total_pages = (total_count + limit - 1) // limit  # Ceiling division
    return jsonify({
        'events': events,
        'total': total_count,
        'page': page,
        'limit': limit,
        'total_pages': total_pages
    })

@app.route('/api/dashboard/device_activity', methods=['GET'])
def get_device_activity():
    """Get detailed activity for a specific device"""
    hostname = request.args.get('hostname')
    if not hostname:
        return jsonify({'error': 'hostname parameter required'}), 400
    
    hours = int(request.args.get('hours', 24))
    
    conn = get_db()
    cursor = conn.cursor()
    
    # Get device info
    cursor.execute('SELECT * FROM devices WHERE hostname = ?', (hostname,))
    device = cursor.fetchone()
    
    if not device:
        conn.close()
        return jsonify({'error': 'Device not found'}), 404
    
    # Get mouse activity statistics
    cursor.execute('''
        SELECT 
            SUM(CASE WHEN event_type = 'mouse_active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN event_type = 'mouse_idle' THEN 1 ELSE 0 END) as idle_count,
            MAX(CASE WHEN event_type = 'mouse_active' THEN timestamp END) as last_active
        FROM events
        WHERE hostname = ? 
        AND event_type IN ('mouse_active', 'mouse_idle')
        AND datetime(timestamp) > datetime('now', '-{} hours')
    '''.format(hours), (hostname,))
    
    mouse_stats = cursor.fetchone()
    
    # Get recent activity events
    cursor.execute('''
        SELECT timestamp, event_type, data
        FROM events
        WHERE hostname = ?
        AND datetime(timestamp) > datetime('now', '-{} hours')
        ORDER BY timestamp DESC
        LIMIT 50
    '''.format(hours), (hostname,))
    
    recent_activity = []
    for row in cursor.fetchall():
        event_data = json.loads(row['data'])
        activity_item = {
            'timestamp': row['timestamp'],
            'type': row['event_type']
        }
        
        # Add relevant data based on event type
        if row['event_type'] == 'domain_visit':
            activity_item['url'] = event_data.get('url', 'Unknown')
            activity_item['process_name'] = event_data.get('process_name', '')
        elif row['event_type'] == 'key_count':
            # Include keystroke info if available
            activity_item['keystrokes'] = event_data.get('keystrokes', [])
        
        # Always include process_name if available
        if 'process_name' in event_data:
            activity_item['process_name'] = event_data['process_name']
            
        recent_activity.append(activity_item)
    
    conn.close()
    
    return jsonify({
        'hostname': device['hostname'],
        'platform': device['platform'],
        'last_active': mouse_stats['last_active'],
        'mouse_active_count': mouse_stats['active_count'] or 0,
        'mouse_idle_count': mouse_stats['idle_count'] or 0,
        'recent_activity': recent_activity
    })

# System Tray functionality
class ServerTrayApp:
    def __init__(self, host='0.0.0.0', port=5000):
        self.host = host
        self.port = port
        self.icon = None
        self.flask_thread = None
        self.running = False
        
    def start_flask(self):
        """Start Flask server in a separate thread"""
        self.running = True
        init_db()
        app.run(host=self.host, port=self.port, debug=False, use_reloader=False)
    
    def get_status(self):
        """Get server status information"""
        uptime = datetime.now() - stats['start_time']
        hours = int(uptime.total_seconds() // 3600)
        minutes = int((uptime.total_seconds() % 3600) // 60)
        
        last_event_str = "Never"
        if stats['last_event']:
            last_event_str = stats['last_event'].strftime('%Y-%m-%d %H:%M:%S')
        
        status = f"""Activity Logger Server
        
Status: Running
Host: {self.host}:{self.port}
Uptime: {hours}h {minutes}m

Total Events: {stats['total_events']}
Active Devices: {stats['active_devices']}
Last Event: {last_event_str}

Dashboard: http://localhost:{self.port}
"""
        return status
    
    def show_status(self):
        """Show status in a message box"""
        if win32api:
            status = self.get_status()
            win32api.MessageBox(0, status, "Server Status", win32con.MB_OK | win32con.MB_ICONINFORMATION)
    
    def open_dashboard(self):
        """Open dashboard in browser"""
        webbrowser.open(f'http://localhost:{self.port}')
    
    def create_tray_menu(self):
        """Create system tray menu"""
        return pystray.Menu(
            pystray.MenuItem("Activity Logger Server", None, enabled=False),
            pystray.Menu.SEPARATOR,
            pystray.MenuItem("View Status", self.show_status),
            pystray.MenuItem("Open Dashboard", self.open_dashboard)
        )
    
    def run(self):
        """Run the application with system tray"""
        if not pystray:
            print("Error: pystray not installed. Install with: pip install pystray pillow")
            sys.exit(1)
        
        # Start Flask in background thread
        self.flask_thread = threading.Thread(target=self.start_flask, daemon=True)
        self.flask_thread.start()
        
        # Create tray icon
        icon_image = create_tray_icon()
        self.icon = pystray.Icon(
            "activity_logger_server",
            icon_image,
            "Activity Logger Server",
            menu=self.create_tray_menu()
        )
        
        # Run the tray icon (this blocks)
        self.icon.run()

def create_tray_icon():
    """Create a system tray icon image (green circle with 'S' for Server)"""
    # Create a 64x64 image
    width = 64
    height = 64
    image = Image.new('RGB', (width, height), color='white')
    dc = ImageDraw.Draw(image)
    
    # Draw green circle
    dc.ellipse([4, 4, width-4, height-4], fill='#28a745', outline='#1e7e34', width=2)
    
    # Draw 'S' text
    dc.text((width//2 - 8, height//2 - 12), 'S', fill='white')
    
    return image

if __name__ == '__main__':
    import argparse
    
    parser = argparse.ArgumentParser(description='Activity Logger Server')
    parser.add_argument('--host', default='0.0.0.0', help='Host to bind to')
    parser.add_argument('--port', type=int, default=5000, help='Port to bind to')
    parser.add_argument('--auth-key', help='Authentication key')
    parser.add_argument('--license-key', help='License key')
    
    args = parser.parse_args()
    
    if args.auth_key:
        app.config['AUTH_KEY'] = args.auth_key
    if args.license_key:
        app.config['LICENSE_KEY'] = args.license_key
        # Set session start when license provided
        app.config['SESSION_START'] = datetime.now()
    
    # Run in system tray mode
    tray_app = ServerTrayApp(host=args.host, port=args.port)
    tray_app.run()
