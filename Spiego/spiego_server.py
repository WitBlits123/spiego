"""
spiego_server.py

Spiego Activity Logger Server - System Tray Application
Launches PHP Laravel server and provides system tray interface.
"""
import os
import sys
import subprocess
import threading
import webbrowser
import time
from pathlib import Path

try:
    import pystray
    from PIL import Image, ImageDraw
except ImportError:
    print("Error: pystray and Pillow required. Install with: pip install pystray pillow")
    sys.exit(1)

class SpiegoServer:
    def __init__(self):
        self.php_process = None
        self.scheduler_thread = None
        self.scheduler_running = False
        self.server_host = "0.0.0.0"
        self.server_port = 5000
        self.icon = None
        self.running = False
        
        # Determine base path (works for both development and PyInstaller)
        if getattr(sys, 'frozen', False):
            self.base_path = Path(sys._MEIPASS)
        else:
            self.base_path = Path(__file__).parent
        
        # Read config from .env file
        self.load_config()
    
    def load_config(self):
        """Load configuration from .env file"""
        env_file = self.base_path / '.env'
        if env_file.exists():
            with open(env_file, 'r') as f:
                for line in f:
                    if line.startswith('APP_URL='):
                        url = line.split('=')[1].strip()
                        if ':' in url:
                            port = url.split(':')[-1]
                            try:
                                self.server_port = int(port)
                            except:
                                pass
    
    def start_php_server(self):
        """Start the PHP Laravel server"""
        try:
            # Find PHP executable
            php_exe = self.find_php()
            if not php_exe:
                print("Error: PHP not found in system PATH")
                return False
            
            # Change to Laravel directory
            os.chdir(self.base_path)
            
            # Start PHP server
            cmd = [
                php_exe,
                'artisan',
                'serve',
                f'--host={self.server_host}',
                f'--port={self.server_port}'
            ]
            
            print(f"Starting Spiego server on http://127.0.0.1:{self.server_port}")
            
            # Start as background process
            self.php_process = subprocess.Popen(
                cmd,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                creationflags=subprocess.CREATE_NO_WINDOW if sys.platform == 'win32' else 0
            )
            
            # Wait a moment for server to start
            time.sleep(2)
            
            # Check if process is still running
            if self.php_process.poll() is None:
                print("✓ Spiego server started successfully")
                self.running = True
                return True
            else:
                print("✗ Failed to start Spiego server")
                return False
                
        except Exception as e:
            print(f"Error starting PHP server: {e}")
            return False
    
    def find_php(self):
        """Find PHP executable in system PATH or bundled location"""
        # Check if PHP is bundled
        if getattr(sys, 'frozen', False):
            bundled_php = self.base_path / 'php' / 'php.exe'
            if bundled_php.exists():
                return str(bundled_php)
        
        # Check system PATH
        import shutil
        php = shutil.which('php')
        return php
    
    def run_scheduler(self):
        """Run Laravel scheduler every minute in background thread"""
        php_exe = self.find_php()
        if not php_exe:
            print("Warning: Cannot run scheduler - PHP not found", flush=True)
            return
        
        print("Starting Laravel scheduler thread...", flush=True)
        
        iteration = 0
        while self.scheduler_running:
            try:
                iteration += 1
                print(f"[Scheduler] Running iteration {iteration}...", flush=True)
                
                # Run artisan schedule:run
                result = subprocess.run(
                    [php_exe, 'artisan', 'schedule:run'],
                    cwd=self.base_path,
                    capture_output=True,
                    text=True,
                    timeout=50  # Should complete well within 60 seconds
                )
                
                # Always print output for debugging
                if result.stdout:
                    print(f"[Scheduler] {result.stdout.strip()}", flush=True)
                
                if result.stderr:
                    print(f"[Scheduler Error] {result.stderr.strip()}", flush=True)
                    
            except subprocess.TimeoutExpired:
                print("[Scheduler] Warning: schedule:run timed out", flush=True)
            except Exception as e:
                print(f"[Scheduler] Error: {e}", flush=True)
            
            # Wait 60 seconds before next run
            print(f"[Scheduler] Waiting 60 seconds until next run...", flush=True)
            time.sleep(60)
    
    def start_scheduler(self):
        """Start the scheduler thread"""
        if not self.scheduler_running:
            self.scheduler_running = True
            self.scheduler_thread = threading.Thread(target=self.run_scheduler, daemon=True)
            self.scheduler_thread.start()
            print("✓ Scheduler thread started (checks every 60 seconds)")
    
    def stop_scheduler(self):
        """Stop the scheduler thread"""
        if self.scheduler_running:
            print("Stopping scheduler thread...")
            self.scheduler_running = False
            if self.scheduler_thread:
                self.scheduler_thread.join(timeout=2)
            print("✓ Scheduler stopped")
    
    def stop_server(self):
        """Stop the PHP server"""
        if self.php_process:
            print("Stopping Spiego server...")
            self.php_process.terminate()
            try:
                self.php_process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                self.php_process.kill()
            self.running = False
            print("✓ Server stopped")
        
        # Also stop scheduler
        self.stop_scheduler()
    
    def open_dashboard(self, icon=None, item=None):
        """Open dashboard in browser"""
        url = f"http://127.0.0.1:{self.server_port}"
        webbrowser.open(url)
    
    def show_status(self, icon=None, item=None):
        """Show server status in message box"""
        try:
            import win32api
            import win32con
            status = "Running" if self.running else "Stopped"
            message = f"Spiego Activity Logger Server\n\nStatus: {status}\nPort: {self.server_port}"
            win32api.MessageBox(0, message, "Spiego Server", win32con.MB_OK | win32con.MB_ICONINFORMATION)
        except ImportError:
            print(f"Status: {'Running' if self.running else 'Stopped'}")
    
    def quit_app(self, icon=None, item=None):
        """Stop server and quit application"""
        self.stop_server()
        if self.icon:
            self.icon.stop()
    
    def create_tray_icon(self):
        """Create system tray icon (eye for Spiego)"""
        width = 64
        height = 64
        image = Image.new('RGB', (width, height), color='white')
        draw = ImageDraw.Draw(image)
        
        # Draw eye shape
        # Outer eye
        draw.ellipse([10, 20, 54, 44], fill='#667eea', outline='#764ba2')
        # Pupil
        draw.ellipse([26, 26, 38, 38], fill='white')
        # Inner pupil
        draw.ellipse([29, 29, 35, 35], fill='#764ba2')
        
        return image
    
    def create_tray_menu(self):
        """Create system tray menu"""
        return pystray.Menu(
            pystray.MenuItem("Spiego Activity Logger", None, enabled=False),
            pystray.Menu.SEPARATOR,
            pystray.MenuItem("View Status", self.show_status),
            pystray.MenuItem("Open Dashboard", self.open_dashboard),
            pystray.Menu.SEPARATOR,
            pystray.MenuItem("Exit", self.quit_app)
        )
    
    def run(self):
        """Run the application"""
        # Start PHP server
        if not self.start_php_server():
            print("Failed to start server. Exiting...")
            return 1
        
        # Start scheduler thread
        self.start_scheduler()
        
        # Create tray icon
        icon_image = self.create_tray_icon()
        self.icon = pystray.Icon(
            "spiego_server",
            icon_image,
            "Spiego Activity Logger",
            menu=self.create_tray_menu()
        )
        
        # Run tray icon (blocks until quit)
        try:
            self.icon.run()
        except KeyboardInterrupt:
            pass
        finally:
            self.stop_server()
        
        return 0

def main():
    """Main entry point"""
    print("=" * 50)
    print("Spiego Activity Logger Server")
    print("=" * 50)
    
    server = SpiegoServer()
    exit_code = server.run()
    sys.exit(exit_code)

if __name__ == '__main__':
    main()
