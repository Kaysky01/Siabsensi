"""
Device Info Utility for SIABSEN Python Backend
Detects hardware manufacturer, model, hostname, and OS details.
Uses caching to ensure ZERO overhead during scan execution.
"""

import os
import platform
import socket
import subprocess
import logging
from typing import Dict, Any

logger = logging.getLogger(__name__)

# In-memory cache for hardware info
_CACHED_DEVICE_INFO: Dict[str, Any] = {}

def get_device_info() -> Dict[str, Any]:
    """Get system hardware info (brand, model, hostname, OS) with caching."""
    global _CACHED_DEVICE_INFO
    if _CACHED_DEVICE_INFO:
        return _CACHED_DEVICE_INFO

    hostname = socket.gethostname()
    os_name = f"{platform.system()} {platform.release()}"
    brand = "Unknown"
    model = "PC/Laptop"

    if platform.system() == "Windows":
        try:
            # Run PowerShell command to get Manufacturer and Model cleanly
            cmd = "Get-CimInstance Win32_ComputerSystem | Select-Object Manufacturer, Model | ConvertTo-Json"
            res = subprocess.run(
                ["powershell", "-NoProfile", "-Command", cmd],
                capture_output=True,
                text=True,
                timeout=3
            )
            if res.returncode == 0 and res.stdout.strip():
                import json
                info = json.loads(res.stdout)
                brand = (info.get("Manufacturer") or "Unknown").strip()
                model = (info.get("Model") or "PC/Laptop").strip()
        except Exception as e:
            logger.debug(f"PowerShell hardware lookup failed: {e}")
            try:
                # Fallback to WMIC
                res_brand = subprocess.run(["wmic", "csproduct", "get", "vendor"], capture_output=True, text=True, timeout=2)
                res_model = subprocess.run(["wmic", "csproduct", "get", "name"], capture_output=True, text=True, timeout=2)
                if res_brand.returncode == 0:
                    lines = [l.strip() for l in res_brand.stdout.splitlines() if l.strip()]
                    if len(lines) > 1:
                        brand = lines[1]
                if res_model.returncode == 0:
                    lines = [l.strip() for l in res_model.stdout.splitlines() if l.strip()]
                    if len(lines) > 1:
                        model = lines[1]
            except Exception as e_wmic:
                logger.debug(f"WMIC hardware lookup failed: {e_wmic}")

    # Build clean device label
    if brand != "Unknown" and model != "PC/Laptop":
        full_name = f"{brand} {model}"
    elif brand != "Unknown":
        full_name = f"{brand} Computer"
    else:
        full_name = f"PC ({hostname})"

    _CACHED_DEVICE_INFO = {
        "brand": brand,
        "model": model,
        "hostname": hostname,
        "os": os_name,
        "full_name": full_name,
        "device_label": f"{full_name} [{hostname}]"
    }

    return _CACHED_DEVICE_INFO

# Pre-populate cache at module import time
try:
    get_device_info()
except Exception as _e:
    pass
