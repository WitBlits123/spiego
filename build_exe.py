"""
build_exe.py

Builds a standalone Windows executable from activity_logger_client.py using PyInstaller.
The resulting EXE will be in the 'dist' folder.
"""
import subprocess
import sys
import os

def build_exe():
    print("Building activity_logger_client.exe...")
    print("This may take a few minutes...\n")
    
    # PyInstaller command
    cmd = [
        sys.executable,
        '-m', 'PyInstaller',
        '--onefile',  # Single executable file
        '--name', 'ActivityLogger',  # Name of the executable
        '--icon', 'NONE',  # You can add an .ico file later
        '--add-data', 'config.ini;.',  # Include config.ini
        '--noconsole',  # No console window (change to --console if you want to see output)
        '--clean',  # Clean cache
        'activity_logger_client.py'
    ]
    
    try:
        result = subprocess.run(cmd, check=True, capture_output=True, text=True)
        print(result.stdout)
        print("\n✅ Build successful!")
        print(f"Executable location: {os.path.abspath('dist/ActivityLogger.exe')}")
        print("\nTo run the executable:")
        print("1. Copy dist/ActivityLogger.exe to target machine")
        print("2. Place config.ini in the same folder as ActivityLogger.exe")
        print("3. Run ActivityLogger.exe")
        
    except subprocess.CalledProcessError as e:
        print(f"❌ Build failed: {e}")
        print(e.stderr)
        return False
    
    return True

if __name__ == '__main__':
    # Check if PyInstaller is installed
    try:
        import PyInstaller
    except ImportError:
        print("PyInstaller not found. Installing...")
        subprocess.run([sys.executable, '-m', 'pip', 'install', 'pyinstaller'], check=True)
    
    build_exe()
