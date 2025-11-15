saya akan mengajukan sejumlah pertanyaan. siapkan solusinya dalam bentuk.md.

1. apakah ketika status sakit atau izin yang nilai defaultnya pending di database berubah karena adanya aktivitas di approve.php atau php lain calculate_status_kehadiran langsung akan melakukan update juga?
2. bagaimana jika pegawai izin, tapi status masih pending, karena takut tidak dianggap izin dan kebetulan tempat acara yang hendak dia hadiri dekat dengan cabang dan ia memilih singgah untuk absen. apakah kemudian ketika izinnya approved dia akan dianggap hadir atau dianggap izin? dan seperti pertanyaan pertama, apakah itu langsung akan terupdate?
3. minimal durasi kerja superadmin 8 jam kerja.
4. klasifikasi keterlambatan pegawai:
a. cek shift. jika ada shift dan terlambat di bawah 20 menit. cuman status terlambat tanpa potongan.
b. jika ada shift dan terlambat 20-39 menit 59 detik. potong tunjangan transport.
c. jika ada shift dan terlambat 40 menit. potong tunjangan transport dan tunjangan makan.
d. jika tidak ada shift dan status overwork approved. dan terlambat di atas 1 jam atau 60 menit. potong 6250 per jam terlambat. misalnya terlambat 2 jam; potong 6250+6250; dan seterusnya
5. jam kerja pegawai juga tidak ada minimal. harus ikuti jadwal shift yang berlaku. 
6. semua output dari calculate status kehadiran ini, harus diteruskan ke rekapabsen.php. view_absensi.php. mainpage.php. dan overview.php