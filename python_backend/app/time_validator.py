"""
Time Validation Module for Attendance System
Handles check-in and check-out time validation against daily schedules
"""

import logging
from datetime import datetime, time as dt_time, timedelta
from typing import Dict, Optional, Tuple
from pathlib import Path
from app.timezone_utils import get_current_time, convert_to_app_timezone, get_timezone_name

logger = logging.getLogger('TimeValidator')


class TimeValidator:
    """Validates attendance times against schedule rules"""
    
    def __init__(self, db_manager):
        self.db = db_manager
        self._grace_period_cache = None
        self._last_cache_time = None
        self._cache_version = None
        self._cache_version_file = Path('data/.schedule_cache_version')
        logger.info(f"TimeValidator initialized with timezone: {get_timezone_name()}")
    
    def _get_cache_version(self) -> int:
        """Get current cache version from file (timestamp written by Laravel)"""
        try:
            if self._cache_version_file.exists():
                return int(self._cache_version_file.read_text().strip())
        except Exception as e:
            logger.warning(f"Failed to read cache version: {e}")
        return 0
    
    def _should_invalidate_cache(self) -> bool:
        """Check if cache should be invalidated based on version file"""
        current_version = self._get_cache_version()
        if self._cache_version is None or current_version != self._cache_version:
            logger.info(f"Cache version changed: {self._cache_version} -> {current_version}")
            self._cache_version = current_version
            return True
        return False
    
    def get_grace_period_minutes(self) -> int:
        """Get grace period with 5-minute cache and version-based invalidation"""
        now = get_current_time()
        
        # Check if cache should be invalidated due to schedule update
        if self._should_invalidate_cache():
            self._grace_period_cache = None
            self._last_cache_time = None
            logger.info("Grace period cache invalidated due to schedule update")
        
        # Check time-based cache expiration (5 minutes)
        if (self._grace_period_cache is None or 
            self._last_cache_time is None or 
            (now - self._last_cache_time).seconds > 300):
            self._grace_period_cache = self.db.get_grace_period_minutes()
            self._last_cache_time = now
            logger.info(f"Grace period loaded: {self._grace_period_cache} minutes")
        return self._grace_period_cache
    
    def parse_time(self, time_value) -> dt_time:
        """
        Parse time from various formats to datetime.time object
        Handles: time objects, timedelta, datetime, string
        """
        if isinstance(time_value, dt_time):
            return time_value
        elif isinstance(time_value, timedelta):
            # Convert timedelta to time
            total_seconds = int(time_value.total_seconds())
            hours = (total_seconds // 3600) % 24
            minutes = (total_seconds % 3600) // 60
            seconds = total_seconds % 60
            return dt_time(hours, minutes, seconds)
        elif isinstance(time_value, datetime):
            return time_value.time()
        elif isinstance(time_value, str):
            # Try parsing HH:MM:SS or HH:MM format
            try:
                return datetime.strptime(time_value, '%H:%M:%S').time()
            except ValueError:
                return datetime.strptime(time_value, '%H:%M').time()
        else:
            raise ValueError(f"Cannot parse time from: {type(time_value)} - {time_value}")
    
    def calculate_minutes_difference(self, time1: dt_time, time2: dt_time) -> int:
        """
        Calculate difference in minutes between two times
        Returns positive if time1 > time2, negative if time1 < time2
        """
        # Convert to datetime for calculation
        today = datetime.now().date()
        dt1 = datetime.combine(today, time1)
        dt2 = datetime.combine(today, time2)
        diff = (dt1 - dt2).total_seconds() / 60
        return int(diff)
    
    def validate_check_in(self, current_time: datetime, schedule: Dict) -> Dict:
        """
        Validate check-in time against schedule (timezone-aware)
        
        Returns dict with:
        - allowed (bool): Whether check-in is allowed
        - is_late (bool): Whether check-in is late
        - late_duration (int): Duration of lateness in minutes
        - reason (str): Reason for rejection if not allowed
        - message (str): User-friendly message
        """
        if not schedule:
            return {
                'allowed': False,
                'is_late': False,
                'late_duration': 0,
                'reason': 'no_schedule',
                'message': 'Tidak ada jadwal absensi untuk hari ini'
            }
        
        try:
            # Ensure current_time is timezone-aware
            current_time = convert_to_app_timezone(current_time)
            
            # Parse schedule times
            check_in_start = self.parse_time(schedule['check_in_start'])
            check_in_end = self.parse_time(schedule['check_in_end'])
            
            current_time_only = current_time.time()
            grace_period_minutes = self.get_grace_period_minutes()
            
            # Calculate grace period end time (timezone-aware)
            check_in_end_dt = datetime.combine(current_time.date(), check_in_end)
            check_in_end_dt = convert_to_app_timezone(check_in_end_dt.replace(tzinfo=current_time.tzinfo))
            grace_end_dt = check_in_end_dt + timedelta(minutes=grace_period_minutes)
            grace_end_time = grace_end_dt.time()
            
            logger.info(f"[CHECK-IN VALIDATION] Current: {current_time_only} ({get_timezone_name()}), "
                       f"Start: {check_in_start}, End: {check_in_end}, "
                       f"Grace End: {grace_end_time}")
            
            # Too early - before check_in_start
            if current_time_only < check_in_start:
                return {
                    'allowed': False,
                    'is_late': False,
                    'late_duration': 0,
                    'reason': 'too_early',
                    'message': f'Absen masuk belum dibuka. Absen masuk mulai jam {check_in_start.strftime("%H:%M")}'
                }
            
            # On time - between check_in_start and check_in_end
            if check_in_start <= current_time_only <= check_in_end:
                return {
                    'allowed': True,
                    'is_late': False,
                    'late_duration': 0,
                    'reason': 'on_time',
                    'message': 'Check-in berhasil (tepat waktu)'
                }
            
            # Late but within grace period - between check_in_end and grace_end
            if check_in_end < current_time_only <= grace_end_time:
                late_minutes = self.calculate_minutes_difference(current_time_only, check_in_end)
                return {
                    'allowed': True,
                    'is_late': True,
                    'late_duration': late_minutes,
                    'reason': 'late',
                    'message': f'Check-in berhasil (TELAT {late_minutes} menit)'
                }
            
            # Too late - after grace period
            return {
                'allowed': False,
                'is_late': False,
                'late_duration': 0,
                'reason': 'too_late',
                'message': f'Absen masuk sudah ditutup. Batas akhir jam {grace_end_time.strftime("%H:%M")}'
            }
            
        except Exception as e:
            logger.error(f"Error validating check-in: {e}", exc_info=True)
            return {
                'allowed': False,
                'is_late': False,
                'late_duration': 0,
                'reason': 'error',
                'message': f'Error validasi waktu: {str(e)}'
            }
    
    def validate_check_out(self, current_time: datetime, schedule: Dict) -> Dict:
        """
        Validate check-out time against schedule (timezone-aware)
        
        Returns dict with:
        - allowed (bool): Whether check-out is allowed
        - reason (str): Reason for rejection if not allowed
        - message (str): User-friendly message
        """
        if not schedule:
            return {
                'allowed': False,
                'reason': 'no_schedule',
                'message': 'Tidak ada jadwal absensi untuk hari ini'
            }
        
        try:
            # Ensure current_time is timezone-aware
            current_time = convert_to_app_timezone(current_time)
            
            # Parse schedule times
            check_out_start = self.parse_time(schedule['check_out_start'])
            check_out_end = self.parse_time(schedule['check_out_end'])
            
            current_time_only = current_time.time()
            
            logger.info(f"[CHECK-OUT VALIDATION] Current: {current_time_only} ({get_timezone_name()}), "
                       f"Start: {check_out_start}, End: {check_out_end}")
            
            # Too early - before check_out_start
            if current_time_only < check_out_start:
                return {
                    'allowed': False,
                    'reason': 'too_early',
                    'message': f'Belum waktunya check-out. Check-out mulai jam {check_out_start.strftime("%H:%M")}'
                }
            
            # Valid window - between check_out_start and check_out_end
            if check_out_start <= current_time_only <= check_out_end:
                return {
                    'allowed': True,
                    'reason': 'valid',
                    'message': 'Check-out berhasil'
                }
            
            # Too late - after check_out_end
            return {
                'allowed': False,
                'reason': 'too_late',
                'message': f'Waktu check-out sudah ditutup. Batas akhir jam {check_out_end.strftime("%H:%M")}'
            }
            
        except Exception as e:
            logger.error(f"Error validating check-out: {e}", exc_info=True)
            return {
                'allowed': False,
                'reason': 'error',
                'message': f'Error validasi waktu: {str(e)}'
            }
