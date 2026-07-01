"""
Timezone Utility Module for Attendance System
Ensures consistent timezone handling across Python backend
"""

import os
from datetime import datetime, time as dt_time
from zoneinfo import ZoneInfo
import logging

logger = logging.getLogger('TimezoneUtils')

# Default timezone - matches Laravel config (Asia/Jakarta)
DEFAULT_TIMEZONE = 'Asia/Jakarta'

# Get timezone from environment or use default
APP_TIMEZONE = os.getenv('APP_TIMEZONE', DEFAULT_TIMEZONE)

try:
    TZ = ZoneInfo(APP_TIMEZONE)
    logger.info(f"Timezone configured: {APP_TIMEZONE}")
except Exception as e:
    logger.warning(f"Failed to load timezone {APP_TIMEZONE}, falling back to {DEFAULT_TIMEZONE}: {e}")
    TZ = ZoneInfo(DEFAULT_TIMEZONE)
    APP_TIMEZONE = DEFAULT_TIMEZONE


def get_current_time() -> datetime:
    """
    Get current time in application timezone (timezone-aware)
    Returns: datetime object with timezone information
    """
    return datetime.now(TZ)


def get_current_date():
    """
    Get current date in application timezone
    Returns: date object
    """
    return get_current_time().date()


def get_current_time_only() -> dt_time:
    """
    Get current time (time component only) in application timezone
    Returns: time object
    """
    return get_current_time().time()


def make_aware(dt: datetime) -> datetime:
    """
    Convert naive datetime to timezone-aware datetime
    Args:
        dt: Naive datetime object
    Returns: Timezone-aware datetime
    """
    if dt.tzinfo is None:
        return dt.replace(tzinfo=TZ)
    return dt


def convert_to_app_timezone(dt: datetime) -> datetime:
    """
    Convert datetime from any timezone to application timezone
    Args:
        dt: Datetime object (can be naive or aware)
    Returns: Datetime in application timezone
    """
    if dt.tzinfo is None:
        # Assume naive datetime is already in app timezone
        return dt.replace(tzinfo=TZ)
    return dt.astimezone(TZ)


def format_datetime(dt: datetime, format_str: str = '%Y-%m-%d %H:%M:%S') -> str:
    """
    Format datetime to string in application timezone
    Args:
        dt: Datetime object
        format_str: Format string (default: 'YYYY-MM-DD HH:MM:SS')
    Returns: Formatted datetime string
    """
    aware_dt = convert_to_app_timezone(dt)
    return aware_dt.strftime(format_str)


def format_time(t: dt_time, format_str: str = '%H:%M:%S') -> str:
    """
    Format time to string
    Args:
        t: Time object
        format_str: Format string (default: 'HH:MM:SS')
    Returns: Formatted time string
    """
    return t.strftime(format_str)


def get_timezone_name() -> str:
    """
    Get the application timezone name
    Returns: Timezone name (e.g., 'Asia/Jakarta')
    """
    return APP_TIMEZONE


def get_timezone_offset() -> str:
    """
    Get current timezone offset (e.g., '+07:00')
    Returns: Timezone offset string
    """
    now = get_current_time()
    offset_seconds = now.utcoffset().total_seconds()
    offset_hours = int(offset_seconds // 3600)
    offset_minutes = int((offset_seconds % 3600) // 60)
    return f"{offset_hours:+03d}:{offset_minutes:02d}"


# For backward compatibility
def now():
    """Alias for get_current_time()"""
    return get_current_time()


def today():
    """Alias for get_current_date()"""
    return get_current_date()
