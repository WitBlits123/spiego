"""
activity_logger.py

Logs device metadata, foreground window (as domain/visit event), mouse moved/idle, and keypress counts.

Writes newline-delimited JSON (JSONL) to an output file.

Designed to run on Windows. Uses only safe logging by default (no raw keystroke contents).
"""
import argparse
import json
import os
import platform
import socket
import sys
import threading
import time
from datetime import datetime, timezone

try:
    import psutil
    import pywin32_system32  # noqa: F401
except Exception:
    # We'll check dependencies at runtime
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
    # timezone-aware UTC timestamp to avoid deprecation warnings on Python 3.13+
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


def write_event(fp, event):
    fp.write(json.dumps(event, ensure_ascii=False) + "\n")
    fp.flush()


class ForegroundWatcher(threading.Thread):
    def __init__(self, fp, poll_interval=1.0):
        super().__init__(daemon=True)
        self.fp = fp
        self.poll = poll_interval
        self.last = None
        self.running = True

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
            # Best-effort: try to extract URL for common browsers using UI Automation
            try:
                # lazy import uiautomation to avoid import-time failures in some environments
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
                        # try direct EditControl on the window
                        edit = None
                        try:
                            if win is not None:
                                edit = win.EditControl()
                        except Exception:
                            edit = None

                        # If we didn't find it directly, search descendants for Edit controls
                        if win is not None and (not edit or not edit.Exists(0, 0)):
                            for c in win.GetChildren():
                                try:
                                    if getattr(c, 'ControlTypeName', '').lower() == 'edit' or getattr(c, 'ClassName', '').lower().find('address') != -1:
                                        edit = c
                                        break
                                except Exception:
                                    continue

                        if edit and edit.Exists(0, 0):
                            # Try multiple ways to get a value from the control
                            try:
                                # uiautomation exposes GetValue() on some controls
                                url = edit.GetValue()
                            except Exception:
                                url = None
                            if not url:
                                try:
                                    vp = edit.GetValuePattern()
                                    # ValuePattern may expose a 'Value' attribute or GetValue
                                    url = getattr(vp, 'Value', None) or (vp.GetValue() if hasattr(vp, 'GetValue') else None)
                                except Exception:
                                    url = None
                    elif 'firefox' in lower:
                        # Firefox: sometimes URL visible in title; we leave url as None and rely on title
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
                    "title": info.get("title"),
                    "process_name": info.get("process_name"),
                    "pid": info.get("pid"),
                    "process_path": info.get("process_path"),
                    "url": info.get("url"),
                }
                write_event(self.fp, ev)
                self.last = key
            time.sleep(self.poll)


class MouseIdleWatcher(threading.Thread):
    def __init__(self, fp, idle_seconds=60, poll_interval=1.0):
        super().__init__(daemon=True)
        self.fp = fp
        self.idle_seconds = idle_seconds
        self.poll = poll_interval
        self.last_move = time.time()
        self.is_idle = False
        self.running = True

        if mouse:
            self.listener = mouse.Listener(on_move=self.on_move)
        else:
            self.listener = None

    def on_move(self, x, y):
        self.last_move = time.time()
        if self.is_idle:
            self.is_idle = False
            write_event(self.fp, {"type": "mouse_active", "timestamp": now_iso(), "x": x, "y": y})

    def run(self):
        if self.listener:
            self.listener.start()
        while self.running:
            idle = time.time() - self.last_move
            if idle >= self.idle_seconds and not self.is_idle:
                self.is_idle = True
                write_event(self.fp, {"type": "mouse_idle", "timestamp": now_iso(), "idle_seconds": idle})
            time.sleep(self.poll)


class KeyCountWatcher(threading.Thread):
    def __init__(self, fp, report_interval=5.0):
        super().__init__(daemon=True)
        self.fp = fp
        self.report_interval = report_interval
        self.count = 0
        self.lock = threading.Lock()
        self.running = True

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
            write_event(self.fp, {"type": "key_count", "timestamp": now_iso(), "count": c})


def check_deps():
    missing = []
    if psutil is None:
        missing.append('psutil')
    if win32gui is None:
        missing.append('pywin32')
    if mouse is None or keyboard is None:
        missing.append('pynput')
    return missing


def main():
    parser = argparse.ArgumentParser(description="Activity logger (safe mode, no raw keystrokes)")
    parser.add_argument('--out', '-o', default=None, help='Output JSONL file path')
    parser.add_argument('--idle', type=int, default=60, help='Idle threshold in seconds')
    parser.add_argument('--poll', type=float, default=1.0, help='Polling interval for foreground checks')
    args = parser.parse_args()

    missing = check_deps()
    if missing:
        print("Missing dependencies:", ", ".join(missing))
        print("Install with: python -m pip install psutil pywin32 pynput uiautomation")
        sys.exit(1)

    out = args.out or os.path.join(os.getcwd(), f"activity_log_{int(time.time())}.jsonl")
    fp = open(out, 'a', encoding='utf-8')

    # write metadata
    write_event(fp, gather_device_metadata())

    fg = ForegroundWatcher(fp, poll_interval=args.poll)
    mi = MouseIdleWatcher(fp, idle_seconds=args.idle, poll_interval=args.poll)
    kc = KeyCountWatcher(fp, report_interval=5.0)

    fg.start()
    mi.start()
    kc.start()

    print(f"Logging to {out}. Press Ctrl+C to stop.")
    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        print("Stopping...")
        fg.running = False
        mi.running = False
        kc.running = False
        time.sleep(0.2)
        fp.close()


if __name__ == '__main__':
    main()
