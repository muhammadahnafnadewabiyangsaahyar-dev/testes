// ============ KALENDER UTILS MODULE ============
(function(window) {
    'use strict';
    
    const KalenderUtils = {};
    
    // Month names
    KalenderUtils.monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
    // Shift details (reference only - actual shifts are loaded dynamically from database)
    // These shift types exist in the database:
    // - pagi: morning shift (07:00-15:00 or 08:00-15:00)
    // - middle: midday shift (12:00-20:00 or 13:00-21:00)
    // - sore: evening shift (15:00-23:00)
    KalenderUtils.shiftDetails = {
        'pagi': { hours: 8, start: '07:00', end: '15:00', label: 'Shift Pagi' },
        'middle': { hours: 8, start: '13:00', end: '21:00', label: 'Shift Middle' },
        'sore': { hours: 8, start: '15:00', end: '23:00', label: 'Shift Sore' },
        'off': { hours: 0, start: '-', end: '-', label: 'Off' }
    };
    
    // Format date as YYYY-MM-DD
    KalenderUtils.formatDate = function(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    };
    
    // Format time from HH:MM:SS to HH:MM
    KalenderUtils.formatTime = function(timeString) {
        if (!timeString) return '00:00';
        return timeString.substring(0, 5);
    };
    
    // Calculate duration between two times
    KalenderUtils.calculateDuration = function(jamMasuk, jamKeluar) {
        if (!jamMasuk || !jamKeluar) return 0;
        
        const startHour = parseInt(jamMasuk.split(':')[0]);
        const startMinute = parseInt(jamMasuk.split(':')[1]);
        const endHour = parseInt(jamKeluar.split(':')[0]);
        const endMinute = parseInt(jamKeluar.split(':')[1]);
        
        let duration = (endHour + endMinute/60) - (startHour + startMinute/60);
        if (duration <= 0) duration += 24; // Handle overnight shifts
        
        return duration;
    };
    
    // Helper function for formatted text output (sprintf-like)
    KalenderUtils.sprintf = function(format, ...args) {
        let i = 0;
        return format.replace(/%([-]?)(\d+)?([sd])/g, (match, leftAlign, width, type) => {
            const arg = args[i++];
            let str = type === 'd' ? String(Math.floor(arg)) : String(arg);
            const padding = width ? (parseInt(width) - str.length) : 0;
            
            if (padding > 0) {
                const pad = ' '.repeat(padding);
                str = leftAlign === '-' ? str + pad : pad + str;
            }
            
            return str;
        });
    };
    
    // Get color for shift type
    KalenderUtils.getShiftColor = function(shiftType) {
        const colors = {
            'pagi': { bg: '#fff3e0', border: '#ff9800', text: '#e65100' },      // Orange - Morning
            'middle': { bg: '#e3f2fd', border: '#2196F3', text: '#0d47a1' },    // Blue - Midday
            'sore': { bg: '#f3e5f5', border: '#9c27b0', text: '#4a148c' },      // Purple - Evening
            'off': { bg: '#f5f5f5', border: '#9e9e9e', text: '#424242' }        // Gray - Off
        };
        
        return colors[shiftType?.toLowerCase()] || colors['middle'];
    };
    
    // Get shift type emoji
    KalenderUtils.getShiftEmoji = function(shiftType) {
        const emojis = {
            'pagi': '🌅',     // Sunrise for morning
            'middle': '☀️',   // Sun for midday
            'sore': '🌆',     // Sunset for evening
            'off': '🚫'       // Off
        };
        
        return emojis[shiftType?.toLowerCase()] || '📅';
    };
    
    // Export to window
    window.KalenderUtils = KalenderUtils;
    
    console.log('✅ KalenderUtils module loaded');
    
})(window);
