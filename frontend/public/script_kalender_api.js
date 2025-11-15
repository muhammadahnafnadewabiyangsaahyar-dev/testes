// ============ KALENDER API MODULE ============
(function(window) {
    'use strict';
    
    const KalenderAPI = {};
    
    // Load cabang list
    KalenderAPI.loadCabangList = async function() {
        try {
            const response = await fetch('api_shift_calendar.php?action=get_cabang');
            const result = await response.json();
            
            if (result.status === 'success' && result.data) {
                console.log('✅ Loaded cabang list:', result.data);
                return result.data;
            } else {
                console.error('Failed to load cabang list:', result.message);
                return [];
            }
        } catch (error) {
            console.error('Error loading cabang list:', error);
            return [];
        }
    };
    
    // Load shift list for outlet
    KalenderAPI.loadShiftList = async function(outletName) {
        if (!outletName) {
            console.log('No outlet name provided');
            return [];
        }
        
        try {
            const response = await fetch(`api_shift_calendar.php?action=get_shifts&outlet=${encodeURIComponent(outletName)}`);
            const result = await response.json();
            
            if (result.status === 'success' && result.data) {
                console.log('✅ Loaded shift list for', outletName, ':', result.data);
                return result.data;
            } else {
                console.error('Failed to load shift list:', result.message);
                return [];
            }
        } catch (error) {
            console.error('Error loading shift list:', error);
            return [];
        }
    };
    
    // Load shift assignments
    KalenderAPI.loadShiftAssignments = async function(cabangId) {
        if (!cabangId) {
            console.log('No cabang selected');
            return {};
        }
        
        try {
            const response = await fetch(`api_shift_calendar.php?action=get_assignments&cabang_id=${cabangId}`);
            const result = await response.json();
            
            console.log('📥 loadShiftAssignments - Raw API response:', result);
            
            if (result.status === 'success' && result.data) {
                console.log('✅ Loaded shift assignments:', result.data);
                console.log('📊 Total assignments from API:', result.data.length);
                
                // Convert array to object keyed by unique ID
                // Also map tanggal_shift to shift_date for consistency
                const assignments = {};
                result.data.forEach(assignment => {
                    // Map field names from API to our expected format
                    const mappedAssignment = {
                        ...assignment,
                        shift_date: assignment.tanggal_shift, // Map tanggal_shift to shift_date
                        pegawai_name: assignment.nama_lengkap,
                        shift_type: assignment.nama_shift
                    };
                    
                    const key = `${assignment.tanggal_shift}-${assignment.user_id}`;
                    assignments[key] = mappedAssignment;
                });
                
                console.log('📦 Converted to object with keys:', Object.keys(assignments).length);
                console.log('🔍 Unique shift types:', [...new Set(result.data.map(a => a.nama_shift))]);
                
                return assignments;
            } else {
                console.error('Failed to load shift assignments:', result.message);
                return {};
            }
        } catch (error) {
            console.error('Error loading shift assignments:', error);
            return {};
        }
    };
    
    // Assign shifts (bulk)
    KalenderAPI.assignShifts = async function(cabangId, assignments) {
        try {
            const response = await fetch('api_shift_calendar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'assign_shifts',
                    cabang_id: cabangId,
                    assignments: assignments
                })
            });
            
            const result = await response.json();
            console.log('assignShifts - API response:', result);
            
            return result;
        } catch (error) {
            console.error('Error assigning shifts:', error);
            return { status: 'error', message: error.message };
        }
    };
    
    // Delete single assignment
    KalenderAPI.deleteAssignment = async function(assignmentId) {
        try {
            const response = await fetch('api_shift_calendar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    id: assignmentId
                })
            });
            
            const result = await response.json();
            console.log('deleteAssignment - API response:', result);
            
            return result;
        } catch (error) {
            console.error('Error deleting assignment:', error);
            return { status: 'error', message: error.message };
        }
    };
    
    // Load pegawai for outlet
    KalenderAPI.loadPegawai = async function(outletName) {
        if (!outletName) {
            console.log('No outlet name provided');
            return [];
        }
        
        try {
            const response = await fetch(`api_shift_calendar.php?action=get_pegawai&outlet=${encodeURIComponent(outletName)}`);
            const result = await response.json();
            
            if (result.status === 'success' && result.data) {
                console.log('✅ Loaded pegawai for', outletName, ':', result.data);
                return result.data;
            } else {
                console.error('Failed to load pegawai:', result.message);
                return [];
            }
        } catch (error) {
            console.error('Error loading pegawai:', error);
            return [];
        }
    };
    
    // Send shift notification email
    KalenderAPI.notifyEmployees = async function(date, cabangId) {
        try {
            const response = await fetch('api_notify_shift.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    date: date,
                    cabang_id: cabangId
                })
            });
            
            const result = await response.json();
            console.log('notifyEmployees - API response:', result);
            
            return result;
        } catch (error) {
            console.error('Error sending notifications:', error);
            return { status: 'error', message: error.message };
        }
    };
    
    // Export to window
    window.KalenderAPI = KalenderAPI;
    
    console.log('✅ KalenderAPI module loaded');
    
})(window);
