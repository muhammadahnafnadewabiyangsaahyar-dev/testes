/**
 * Module untuk handle izin/sakit di kalender
 * Fetch data dari API dan render badge di kalender
 */

// Cache untuk data izin/sakit
let izinSakitCache = {};

/**
 * Fetch data izin/sakit dari API
 * @param {number} userId - ID user
 * @param {string} startDate - Tanggal mulai (YYYY-MM-DD)
 * @param {string} endDate - Tanggal akhir (YYYY-MM-DD)
 * @returns {Promise<Array>} Array of izin/sakit data
 */
async function fetchIzinSakitData(userId, startDate, endDate) {
    const cacheKey = `${userId}_${startDate}_${endDate}`;
    
    // Return from cache if available
    if (izinSakitCache[cacheKey]) {
        console.log('📋 Izin/Sakit data loaded from cache');
        return izinSakitCache[cacheKey];
    }
    
    try {
        const url = `api_izin_sakit.php?user_id=${userId}&start=${startDate}&end=${endDate}`;
        console.log(`📡 Fetching izin/sakit data: ${url}`);
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            console.log(`✅ Loaded ${result.count} izin/sakit records`);
            izinSakitCache[cacheKey] = result.data;
            return result.data;
        } else {
            console.error('❌ API error:', result.message);
            return [];
        }
    } catch (error) {
        console.error('❌ Fetch error:', error);
        return [];
    }
}

/**
 * Render badge izin/sakit di kalender cell
 * @param {HTMLElement} cell - Calendar cell element
 * @param {Array} izinSakitList - List of izin/sakit for that date
 */
function renderIzinSakitBadges(cell, izinSakitList) {
    if (!cell || !izinSakitList || izinSakitList.length === 0) {
        return;
    }
    
    izinSakitList.forEach(item => {
        const badge = document.createElement('div');
        badge.className = item.type === 'Izin' ? 'badge-izin' : 'badge-sakit';
        
        const icon = item.type === 'Izin' ? 'fa-file-alt' : 'fa-briefcase-medical';
        badge.innerHTML = `<i class="fa ${icon}"></i> ${item.type}`;
        
        // Set title untuk tooltip
        badge.title = `${item.type}: ${item.reason}\n(${item.start_date} s/d ${item.end_date})`;
        
        // Prevent click event propagation (agar tidak trigger modal assign shift)
        badge.addEventListener('click', (e) => {
            e.stopPropagation();
            showIzinSakitDetail(item);
        });
        
        cell.appendChild(badge);
    });
}

/**
 * Show detail izin/sakit dalam modal/alert
 * @param {Object} item - Izin/sakit item
 */
function showIzinSakitDetail(item) {
    const message = `
╔═══════════════════════════════════╗
║  ${item.type.toUpperCase()} DETAIL
╚═══════════════════════════════════╝

👤 Nama: ${item.user_name}
📋 Jabatan: ${item.user_position}
📅 Periode: ${item.start_date} s/d ${item.end_date}
⏱️  Lama: ${item.duration} hari
📝 Alasan: ${item.reason}
✅ Status: ${item.status}
    `.trim();
    
    alert(message);
}

/**
 * Load dan render semua izin/sakit untuk tanggal-tanggal yang visible di kalender
 * @param {Array} userIds - Array of user IDs to load
 * @param {string} startDate - Start date (YYYY-MM-DD)
 * @param {string} endDate - End date (YYYY-MM-DD)
 */
async function loadAndRenderIzinSakit(userIds, startDate, endDate) {
    console.log(`🔄 Loading izin/sakit for ${userIds.length} users...`);
    
    // Fetch data untuk semua users
    const promises = userIds.map(userId => fetchIzinSakitData(userId, startDate, endDate));
    const allData = await Promise.all(promises);
    
    // Flatten array
    const flatData = allData.flat();
    
    // Group by date
    const byDate = {};
    flatData.forEach(item => {
        if (!byDate[item.date]) {
            byDate[item.date] = [];
        }
        byDate[item.date].push(item);
    });
    
    // Render badges untuk setiap tanggal
    Object.keys(byDate).forEach(date => {
        // Find calendar cell for this date
        const cell = document.querySelector(`td[data-date="${date}"]`);
        if (cell) {
            renderIzinSakitBadges(cell, byDate[date]);
        }
    });
    
    console.log(`✅ Rendered izin/sakit badges for ${Object.keys(byDate).length} dates`);
}

/**
 * Clear semua badge izin/sakit dari kalender
 */
function clearIzinSakitBadges() {
    document.querySelectorAll('.badge-izin, .badge-sakit').forEach(badge => {
        badge.remove();
    });
}

/**
 * Clear cache (useful saat ada perubahan data)
 */
function clearIzinSakitCache() {
    izinSakitCache = {};
    console.log('🗑️  Izin/Sakit cache cleared');
}

// Export functions untuk digunakan di script lain
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        fetchIzinSakitData,
        renderIzinSakitBadges,
        loadAndRenderIzinSakit,
        clearIzinSakitBadges,
        clearIzinSakitCache
    };
}

console.log('✅ Izin/Sakit module loaded');
