"""
activity_logger_client.py

Logs device metadata, foreground window (as domain/visit event), mouse moved/idle, and keypress counts.
Sends events to a remote server via HTTP POST with auth key validation.
Falls back to local JSONL file if server is unavailable.

Designed to run on Windows. Uses only safe logging by default (no raw keystroke contents).
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

ui = None


def now_iso():
    return datetime.now(timezone.utc).isoformat()


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
                # Collect events for send_interval seconds
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
                print(f"Error in sender loop: {e}")

    def _send_batch(self, batch):
        if not requests:
            # Fallback to file if requests not available
            self._write_to_fallback(batch)
            return

        for attempt in range(self.retry_attempts):
            try:
                headers = {'Authorization': f'Bearer {self.auth_key}', 'Content-Type': 'application/json'}
                resp = requests.post(self.server_url, json={'events': batch}, headers=headers, timeout=10)
                if resp.status_code == 200:
                    return
                else:
                    print(f"Server returned {resp.status_code}: {resp.text}")
            except Exception as e:
                print(f"Failed to send batch (attempt {attempt+1}/{self.retry_attempts}): {e}")
                time.sleep(1)

        # All retries failed, write to fallback
        self._write_to_fallback(batch)

    def _write_to_fallback(self, batch):
        try:
            with open(self.fallback_file, 'a', encoding='utf-8') as f:
                for event in batch:
                    f.write(json.dumps(event, ensure_ascii=False) + "\n")
        except Exception as e:
            print(f"Error writing to fallback file: {e}")

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
        while self.running:
            info = self.get_foreground_info()
            key = (info.get("pid"), info.get("title"))
            if key != self.last:
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
                self.last = key
            time.sleep(self.poll)


class MouseIdleWatcher(threading.Thread):
    def __init__(self, event_queue, idle_seconds=60, poll_interval=1.0, hostname=None):
        super().__init__(daemon=True)
        self.event_queue = event_queue
        self.idle_seconds = idle_seconds
        self.poll = poll_interval
        self.last_move = time.time()
        self.is_idle = False
        self.running = True
        self.hostname = hostname or socket.gethostname()

        if mouse:
            self.listener = mouse.Listener(on_move=self.on_move)
        else:
            self.listener = None

    def on_move(self, x, y):
        self.last_move = time.time()
        if self.is_idle:
            self.is_idle = False
            self.event_queue.add_event({"type": "mouse_active", "timestamp": now_iso(), "hostname": self.hostname, "x": x, "y": y})

    def run(self):
        if self.listener:
            self.listener.start()
        while self.running:
            idle = time.time() - self.last_move
            if idle >= self.idle_seconds and not self.is_idle:
                self.is_idle = True
                self.event_queue.add_event({"type": "mouse_idle", "timestamp": now_iso(), "hostname": self.hostname, "idle_seconds": idle})
            time.sleep(self.poll)


class KeyCountWatcher(threading.Thread):
    def __init__(self, event_queue, report_interval=5.0, hostname=None):
        super().__init__(daemon=True)
        self.event_queue = event_queue
        self.report_interval = report_interval
        self.count = 0
        self.lock = threading.Lock()
        self.running = True
        self.hostname = hostname or socket.gethostname()

        if keyboard:
            self.k_listener = keyboard.Listener(on_press=self.on_press)
        else:
            self.k_listener = None

    def on_press(self, key):
        with self.lock:
            self.count += 1

    def run(self):
        if self.k_listener:
            self.k_listener.start()
        while self.running:
            time.sleep(self.report_interval)
            with self.lock:
                c = self.count
                self.count = 0
            self.event_queue.add_event({"type": "key_count", "timestamp": now_iso(), "hostname": self.hostname, "count": c})


def check_deps():
    missing = []
    if psutil is None:
        missing.append('psutil')
    if win32gui is None:
        missing.append('pywin32')
    if mouse is None or keyboard is None:
        missing.append('pynput')
    if requests is None:
        missing.append('requests')
    return missing


def load_config(config_path='config.ini'):
    config = configparser.ConfigParser()
    if not os.path.exists(config_path):
        print(f"Config file not found: {config_path}")
        print("Please create a config.ini file. See config.ini.example for reference.")
        sys.exit(1)
    config.read(config_path)
    # Validate required fields
    if not config.get('Security', 'auth_key', fallback=''):
        print("Error: auth_key not set in config.ini [Security] section")
        sys.exit(1)
    return config


def main():
    parser = argparse.ArgumentParser(description="Activity logger client (sends to remote server)")
    parser.add_argument('--config', '-c', default='config.ini', help='Path to config file')
    args = parser.parse_args()

    config = load_config(args.config)

    missing = check_deps()
    if missing:
        print("Missing dependencies:", ", ".join(missing))
        print("Install with: python -m pip install psutil pywin32 pynput uiautomation requests")
        sys.exit(1)

    idle_threshold = config.getint('Logging', 'idle_threshold', fallback=60)
    poll_interval = config.getfloat('Logging', 'poll_interval', fallback=1.0)

    event_queue = EventQueue(config)
    
    # Get hostname once
    hostname = socket.gethostname()

    # Send initial metadata
    event_queue.add_event(gather_device_metadata())

    fg = ForegroundWatcher(event_queue, poll_interval=poll_interval, hostname=hostname)
    mi = MouseIdleWatcher(event_queue, idle_seconds=idle_threshold, poll_interval=poll_interval, hostname=hostname)
    kc = KeyCountWatcher(event_queue, report_interval=5.0, hostname=hostname)

    fg.start()
    mi.start()
    kc.start()

    print(f"Activity logger started. Sending to {event_queue.server_url}")
    print("Press Ctrl+C to stop.")
    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        print("Stopping...")
        fg.running = False
        mi.running = False
        kc.running = False
        event_queue.stop()
        time.sleep(0.5)


if __name__ == '__main__':
    main()
