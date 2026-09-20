// resources/js/Utils/date.js

let cachedTimezone = 'Asia/Kolkata';

export function setPanelTimezone(tz) {
  if (tz) cachedTimezone = tz;
}

export function getPanelTimezone() {
  return cachedTimezone || 'Asia/Kolkata';
}

/**
 * Format a date string or timestamp to localized date (e.g. Sep 20, 2026)
 */
export function formatDate(dateVal, options = {}) {
  if (!dateVal) return 'N/A';
  try {
    const d = new Date(dateVal);
    if (isNaN(d.getTime())) return String(dateVal);
    const tz = options.timeZone || getPanelTimezone();
    return d.toLocaleDateString('en-US', {
      timeZone: tz,
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      ...options
    });
  } catch (e) {
    return String(dateVal);
  }
}

/**
 * Format a date string or timestamp to localized date & time (e.g. Sep 20, 2026, 11:30 AM)
 */
export function formatDateTime(dateVal, options = {}) {
  if (!dateVal) return 'N/A';
  try {
    const d = new Date(dateVal);
    if (isNaN(d.getTime())) return String(dateVal);
    const tz = options.timeZone || getPanelTimezone();
    return d.toLocaleString('en-US', {
      timeZone: tz,
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: true,
      ...options
    });
  } catch (e) {
    return String(dateVal);
  }
}

/**
 * Format a date string or timestamp to localized 24h or 12h time (e.g. 11:30:45)
 */
export function formatTime(dateVal, options = {}) {
  if (!dateVal) return 'N/A';
  try {
    const d = new Date(dateVal);
    if (isNaN(d.getTime())) return String(dateVal);
    const tz = options.timeZone || getPanelTimezone();
    return d.toLocaleTimeString('en-US', {
      timeZone: tz,
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
      ...options
    });
  } catch (e) {
    return String(dateVal);
  }
}

export default {
  getPanelTimezone,
  setPanelTimezone,
  formatDate,
  formatDateTime,
  formatTime
};
