// ============ KALENDER SUMMARY MODULE ============
(function(window) {
    'use strict';
    
    const KalenderSummary = {};
    
    // Get date range for current view
    KalenderSummary.getDateRangeForCurrentView = function(currentView, currentDate, currentMonth, currentYear) {
        let startDate, endDate;
        
        if (currentView === 'month') {
            // First and last day of current month
            startDate = new Date(currentYear, currentMonth, 1);
            endDate = new Date(currentYear, currentMonth + 1, 0);
        } else if (currentView === 'week') {
            // Start of week (Monday) to end of week (Sunday)
            const weekStart = new Date(currentDate);
            const day = weekStart.getDay();
            const diff = weekStart.getDate() - day + (day === 0 ? -6 : 1);
            weekStart.setDate(diff);
            
            startDate = new Date(weekStart);
            endDate = new Date(weekStart);
            endDate.setDate(startDate.getDate() + 6);
        } else if (currentView === 'day') {
            // Just the current day
            startDate = new Date(currentDate);
            endDate = new Date(currentDate);
        } else if (currentView === 'year') {
            // First and last day of current year
            startDate = new Date(currentYear, 0, 1);
            endDate = new Date(currentYear, 11, 31);
        }
        
        return {
            start: window.KalenderUtils.formatDate(startDate),
            end: window.KalenderUtils.formatDate(endDate),
            startDate: startDate,
            endDate: endDate
        };
    };
    
    // Get view range name
    KalenderSummary.getViewRangeName = function(currentView, currentDate, currentMonth, currentYear) {
        const monthNames = window.KalenderUtils.monthNames;
        
        if (currentView === 'month') {
            return `${monthNames[currentMonth]} ${currentYear}`;
        } else if (currentView === 'week') {
            const weekStart = new Date(currentDate);
            const day = weekStart.getDay();
            const diff = weekStart.getDate() - day + (day === 0 ? -6 : 1);
            weekStart.setDate(diff);
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 6);
            
            return `${weekStart.getDate()} ${monthNames[weekStart.getMonth()]} - ${weekEnd.getDate()} ${monthNames[weekEnd.getMonth()]} ${weekEnd.getFullYear()}`;
        } else if (currentView === 'day') {
            return `${currentDate.getDate()} ${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
        } else if (currentView === 'year') {
            return `Tahun ${currentYear}`;
        }
        return '';
    };
    
    // Calculate employee summary
    KalenderSummary.calculateEmployeeSummary = function(dateRange, shiftAssignments) {
        console.log('calculateEmployeeSummary - dateRange:', dateRange);
        console.log('calculateEmployeeSummary - shiftAssignments:', shiftAssignments);
        
        const employeeData = {};
        
        if (shiftAssignments && typeof shiftAssignments === 'object') {
            Object.keys(shiftAssignments).forEach(key => {
                const assignment = shiftAssignments[key];
                const assignmentDate = assignment.shift_date;
                
                // Check if assignment is within date range
                if (assignmentDate >= dateRange.start && assignmentDate <= dateRange.end) {
                    const pegawaiName = assignment.nama_lengkap || assignment.pegawai_name || 'Unknown';
                    const pegawaiId = assignment.user_id || assignment.pegawai_id;
                    
                    if (!employeeData[pegawaiId]) {
                        employeeData[pegawaiId] = {
                            id: pegawaiId,
                            name: pegawaiName,
                            totalShifts: 0,
                            totalHours: 0,
                            workDays: new Set(),
                            offDays: 0
                        };
                    }
                    
                    employeeData[pegawaiId].totalShifts++;
                    
                    // Calculate hours
                    if (assignment.jam_masuk && assignment.jam_keluar) {
                        const duration = window.KalenderUtils.calculateDuration(
                            assignment.jam_masuk,
                            assignment.jam_keluar
                        );
                        employeeData[pegawaiId].totalHours += duration;
                    }
                    
                    // Track unique work days
                    if (assignment.shift_type !== 'off' && assignment.nama_shift !== 'Off') {
                        employeeData[pegawaiId].workDays.add(assignmentDate);
                    } else {
                        employeeData[pegawaiId].offDays++;
                    }
                }
            });
        }
        
        // Convert to array and format
        const result = Object.values(employeeData).map(emp => ({
            name: emp.name,
            totalShifts: emp.totalShifts,
            totalHours: Math.round(emp.totalHours * 10) / 10,
            workDays: emp.workDays.size,
            offDays: emp.offDays
        }));
        
        // Sort by name
        result.sort((a, b) => a.name.localeCompare(b.name));
        
        console.log('calculateEmployeeSummary - result:', result);
        return result;
    };
    
    // Calculate shift summary
    KalenderSummary.calculateShiftSummary = function(dateRange, shiftAssignments) {
        console.log('calculateShiftSummary - dateRange:', dateRange);
        
        const shiftData = {};
        
        if (shiftAssignments && typeof shiftAssignments === 'object') {
            Object.keys(shiftAssignments).forEach(key => {
                const assignment = shiftAssignments[key];
                const assignmentDate = assignment.shift_date;
                
                // Check if assignment is within date range
                if (assignmentDate >= dateRange.start && assignmentDate <= dateRange.end) {
                    const shiftName = assignment.nama_shift || assignment.shift_type || 'Unknown';
                    
                    if (!shiftData[shiftName]) {
                        shiftData[shiftName] = 0;
                    }
                    
                    shiftData[shiftName]++;
                }
            });
        }
        
        console.log('calculateShiftSummary - result:', shiftData);
        return shiftData;
    };
    
    // Update summary display
    KalenderSummary.updateSummaryDisplay = function(rangeName, employeeSummary, shiftSummary) {
        console.log('updateSummaryDisplay - employeeSummary:', employeeSummary);
        console.log('updateSummaryDisplay - shiftSummary:', shiftSummary);
        
        // Update employee summary table
        const employeeBody = document.getElementById('employee-summary-body');
        if (employeeBody) {
            if (employeeSummary.length === 0) {
                employeeBody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">Tidak ada data shift untuk periode ini</td></tr>';
            } else {
                employeeBody.innerHTML = '';
                employeeSummary.forEach(emp => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${emp.name}</td>
                        <td style="text-align: center;">${emp.totalShifts}</td>
                        <td style="text-align: center;">${emp.totalHours}</td>
                        <td style="text-align: center;">${emp.workDays}</td>
                        <td style="text-align: center;">${emp.offDays}</td>
                    `;
                    employeeBody.appendChild(row);
                });
            }
        }
        
        // Update shift summary table
        const shiftBody = document.getElementById('shift-summary-body');
        if (shiftBody) {
            if (Object.keys(shiftSummary).length === 0) {
                shiftBody.innerHTML = '<tr><td colspan="2" style="text-align: center; padding: 20px; color: #999;">Tidak ada data shift untuk periode ini</td></tr>';
            } else {
                shiftBody.innerHTML = '';
                
                // Sort by shift name
                const sortedShifts = Object.entries(shiftSummary).sort((a, b) => a[0].localeCompare(b[0]));
                
                sortedShifts.forEach(([shiftName, count]) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${shiftName}</td>
                        <td style="text-align: center;">${count}</td>
                    `;
                    shiftBody.appendChild(row);
                });
            }
        }
        
        // Update under-minimum notification (employees with less than 26 shift days)
        const underMinimumNotif = document.getElementById('under-minimum-notification');
        const underMinimumBody = document.getElementById('under-minimum-body');
        
        if (underMinimumNotif && underMinimumBody) {
            const MINIMUM_SHIFT_DAYS = 26;
            const underMinimumEmployees = employeeSummary.filter(emp => emp.workDays < MINIMUM_SHIFT_DAYS);
            
            if (underMinimumEmployees.length > 0) {
                underMinimumNotif.style.display = 'block';
                underMinimumBody.innerHTML = '';
                
                underMinimumEmployees.forEach(emp => {
                    const shortage = MINIMUM_SHIFT_DAYS - emp.workDays;
                    const percentage = Math.round((emp.workDays / MINIMUM_SHIFT_DAYS) * 100);
                    
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${emp.name}</td>
                        <td style="text-align: center; font-weight: bold; color: #f44336;">${emp.workDays}</td>
                        <td style="text-align: center; font-weight: bold; color: #ff9800;">${shortage} hari</td>
                        <td style="text-align: center;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="flex: 1; background-color: #e0e0e0; border-radius: 10px; height: 20px; overflow: hidden;">
                                    <div style="width: ${percentage}%; background-color: ${percentage < 50 ? '#f44336' : percentage < 80 ? '#ff9800' : '#4CAF50'}; height: 100%;"></div>
                                </div>
                                <span style="font-weight: bold; min-width: 45px;">${percentage}%</span>
                            </div>
                        </td>
                    `;
                    underMinimumBody.appendChild(row);
                });
            } else {
                underMinimumNotif.style.display = 'none';
            }
        }
    };
    
    // Generate CSV content
    KalenderSummary.generateCSVContent = function(dateRange, rangeName, shiftAssignments) {
        let csv = 'Nama Pegawai,Total Shift,Total Jam,Hari Kerja,Hari Off\n';
        
        const employeeSummary = KalenderSummary.calculateEmployeeSummary(dateRange, shiftAssignments);
        const shiftSummary = KalenderSummary.calculateShiftSummary(dateRange, shiftAssignments);
        
        // Employee summary
        employeeSummary.forEach(emp => {
            csv += `"${emp.name}","${emp.totalShifts}","${emp.totalHours}","${emp.workDays}","${emp.offDays}"\n`;
        });
        
        csv += '\n"RINGKASAN PER JENIS SHIFT"\n';
        csv += '"Nama Shift","Jumlah"\n';
        for (const shiftName in shiftSummary) {
            csv += `"${shiftName}","${shiftSummary[shiftName]}"\n`;
        }
        
        return csv;
    };
    
    // Generate TXT content
    KalenderSummary.generateTXTContent = function(employeeSummary, shiftSummary, rangeName, currentCabangName) {
        const sprintf = window.KalenderUtils.sprintf;
        
        let txt = `RINGKASAN SHIFT - ${currentCabangName}\n`;
        txt += `Periode: ${rangeName}\n`;
        txt += `Tanggal Download: ${new Date().toLocaleString('id-ID')}\n`;
        txt += `${'='.repeat(80)}\n\n`;
        
        // Employee summary
        txt += 'RINGKASAN PER PEGAWAI\n';
        txt += `${'─'.repeat(80)}\n`;
        txt += sprintf('%-40s %12s %12s %10s %10s\n', 'Nama Pegawai', 'Total Shift', 'Total Jam', 'Hari Kerja', 'Hari Off');
        txt += `${'─'.repeat(80)}\n`;
        employeeSummary.forEach(emp => {
            txt += sprintf('%-40s %12d %12d %10d %10d\n', 
                emp.name, emp.totalShifts, emp.totalHours, emp.workDays, emp.offDays);
        });
        
        txt += `\n${'='.repeat(80)}\n\n`;
        
        // Shift summary
        txt += 'RINGKASAN PER JENIS SHIFT\n';
        txt += `${'─'.repeat(50)}\n`;
        txt += sprintf('%-40s %10s\n', 'Nama Shift', 'Jumlah');
        txt += `${'─'.repeat(50)}\n`;
        for (const shiftName in shiftSummary) {
            txt += sprintf('%-40s %10d\n', shiftName, shiftSummary[shiftName]);
        }
        
        return txt;
    };
    
    // Filter summary by name
    KalenderSummary.filterSummaryByName = function() {
        const filterInput = document.getElementById('summary-filter');
        if (!filterInput) return;
        
        const filterValue = filterInput.value.toLowerCase().trim();
        const employeeTableBody = document.getElementById('employee-summary-body');
        
        if (!employeeTableBody) return;
        
        const rows = employeeTableBody.getElementsByTagName('tr');
        
        for (let row of rows) {
            const nameCell = row.getElementsByTagName('td')[0];
            if (nameCell) {
                const nameText = nameCell.textContent.toLowerCase();
                if (nameText.includes(filterValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }
        
        console.log('✅ Summary filtered by:', filterValue);
    };
    
    // Export to window
    window.KalenderSummary = KalenderSummary;
    
    console.log('✅ KalenderSummary module loaded');
    
})(window);
